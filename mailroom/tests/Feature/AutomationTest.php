<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Enums\StepType;
use App\Mail\CampaignMail;
use App\Models\Automation;
use App\Models\Campaign;
use App\Services\AutomationBuilder;
use App\Services\TagSync;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

class AutomationTest extends MailroomTestCase
{
    private function welcome(array $attributes = []): Automation
    {
        $audience = $attributes['audience'] ?? $this->audience(['double_opt_in' => false]);
        unset($attributes['audience']);
        $automation = Automation::query()->create($attributes + ['audience_id' => $audience->id, 'name' => 'Welcome', 'trigger' => 'subscribed', 'status' => 'active']);
        app(AutomationBuilder::class)->applyPreset($automation, 'welcome');

        return $automation->fresh();
    }

    public function test_create_from_preset_builds_hidden_email_campaigns(): void
    {
        $audience = $this->audience();
        $this->actingAs($this->admin())->post('/automations', [
            'name' => 'Welcome', 'audience_id' => $audience->id, 'trigger' => 'subscribed', 'preset' => 'welcome',
        ])->assertRedirect();

        $automation = Automation::query()->sole();
        $this->assertSame(['email', 'wait', 'email', 'wait', 'email'], $automation->steps->map(fn ($s) => $s->type->value)->all());
        $this->assertSame(3, Campaign::query()->where('kind', 'automation')->count());
        $this->actingAs($this->admin())->get('/campaigns')->assertDontSee('Welcome · email');
        $this->actingAs($this->admin())->get("/automations/{$automation->id}")->assertOk()->assertSee('Welcome to {{ audience_name }}!', false);
    }

    public function test_signup_runs_the_welcome_series_over_time(): void
    {
        Mail::fake();
        $automation = $this->welcome();

        $this->post("/f/{$automation->audience->public_id}", ['email' => 'new@x.test'])->assertOk();
        Mail::assertSent(CampaignMail::class, 1);
        $run = $automation->runs()->sole();
        $this->assertSame(2, $run->position);

        $this->artisan('automations:run');
        Mail::assertSent(CampaignMail::class, 1); // still waiting 2 days

        $this->travel(2)->days();
        $this->artisan('automations:run');
        Mail::assertSent(CampaignMail::class, 2);

        $this->travel(5)->days();
        $this->artisan('automations:run');
        Mail::assertSent(CampaignMail::class, 3);
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_unsubscribed_contacts_exit_the_journey(): void
    {
        Mail::fake();
        $automation = $this->welcome();
        $contact = $this->contact($automation->audience, 'a@x.test');
        $contact->unsubscribe();

        $this->travel(3)->days();
        $this->artisan('automations:run');

        Mail::assertSent(CampaignMail::class, 1);
        $this->assertSame('exited', $automation->runs()->sole()->status);
    }

    public function test_tag_trigger_and_tag_steps(): void
    {
        Mail::fake();
        $audience = $this->audience();
        $automation = Automation::query()->create(['audience_id' => $audience->id, 'name' => 'VIP', 'trigger' => 'tag_added', 'trigger_value' => 'vip', 'status' => 'active']);
        $builder = app(AutomationBuilder::class);
        $builder->add($automation, StepType::Email);
        $builder->add($automation, StepType::Tag, null, null, ['tag' => 'welcomed']);

        $contact = $this->contact($audience, 'a@x.test');
        app(TagSync::class)->attach($contact, 'other');
        $this->assertSame(0, $automation->runs()->count());

        app(TagSync::class)->attach($contact, 'vip');
        Mail::assertSent(CampaignMail::class, 1);
        $this->assertContains('welcomed', $contact->tags()->pluck('name')->all());
        $this->assertSame('completed', $automation->runs()->sole()->status);
    }

    public function test_paused_or_draft_automations_do_nothing_and_imports_dont_trigger(): void
    {
        Mail::fake();
        $automation = $this->welcome(['status' => 'draft']);
        $this->contact($automation->audience, 'a@x.test');
        $this->assertSame(0, $automation->runs()->count());

        $automation->update(['status' => 'active']);
        $this->actingAs($this->admin())->post("/audiences/{$automation->audience_id}/import", [
            'file' => UploadedFile::fake()->createWithContent('l.csv', "email\nb@x.test\n"), 'status' => 'subscribed', 'consent' => '1',
        ]);
        $this->assertSame(0, $automation->runs()->count());
    }

    public function test_manual_enroll_existing_contacts_once(): void
    {
        Mail::fake();
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test');
        $this->contact($audience, 'b@x.test');
        $automation = $this->welcome(['audience' => $audience]);
        $automation->runs()->delete();

        $user = $this->admin();
        $this->actingAs($user)->post("/automations/{$automation->id}/enroll")->assertSessionHas('status', '2 contacts added to the automation.');
        $this->actingAs($user)->post("/automations/{$automation->id}/enroll")->assertSessionHas('status', '0 contacts added to the automation.');
    }

    public function test_step_management_and_activation_rules(): void
    {
        $audience = $this->audience();
        $user = $this->admin();
        $this->actingAs($user)->post('/automations', ['name' => 'X', 'audience_id' => $audience->id, 'trigger' => 'subscribed', 'preset' => 'blank']);
        $automation = Automation::query()->sole();

        $this->actingAs($user)->post("/automations/{$automation->id}/status", ['status' => 'active'])->assertSessionHasErrors('status');
        $this->actingAs($user)->post("/automations/{$automation->id}/steps", ['type' => 'wait']);
        $this->actingAs($user)->post("/automations/{$automation->id}/steps", ['type' => 'email', 'after' => -1]);
        $this->assertSame(['email', 'wait'], $automation->steps()->get()->map(fn ($s) => $s->type->value)->all());

        $wait = $automation->steps()->where('type', 'wait')->sole();
        $this->actingAs($user)->put("/automations/{$automation->id}/steps/{$wait->id}", ['amount' => 3, 'unit' => 'hours'])->assertRedirect();
        $this->assertSame(10800, $wait->fresh()->delayInSeconds());

        $this->actingAs($user)->post("/automations/{$automation->id}/steps/{$wait->id}/move", ['direction' => 'up']);
        $this->assertSame(['wait', 'email'], $automation->steps()->get()->map(fn ($s) => $s->type->value)->all());

        $this->actingAs($user)->post("/automations/{$automation->id}/status", ['status' => 'active'])->assertSessionHasNoErrors();
        $this->assertTrue($automation->fresh()->isActive());

        $email = $automation->steps()->where('type', 'email')->sole();
        $this->actingAs($user)->delete("/automations/{$automation->id}/steps/{$email->id}");
        $this->assertSame(0, Campaign::query()->count());
    }

    public function test_automation_emails_cannot_be_blasted_as_campaigns(): void
    {
        Mail::fake();
        $automation = $this->welcome(['status' => 'draft']);
        $this->contact($automation->audience, 'a@x.test');
        $email = $automation->steps()->first()->campaign;

        $this->actingAs($this->admin())->post("/campaigns/{$email->id}/send");
        Mail::assertNothingSent();
    }

    public function test_status_change_by_contact_confirm_triggers(): void
    {
        Mail::fake();
        $audience = $this->audience(); // double opt-in
        $automation = $this->welcome(['audience' => $audience]);
        $this->post("/f/{$audience->public_id}", ['email' => 'p@x.test']);
        $this->assertSame(0, $automation->runs()->count());

        $contact = $audience->contacts()->sole();
        $this->assertSame(ContactStatus::Pending, $contact->status);
        $this->get("/confirm/{$contact->token}");
        $this->assertSame(1, $automation->runs()->count());
    }
}
