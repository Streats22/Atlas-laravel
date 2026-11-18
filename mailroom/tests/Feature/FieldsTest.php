<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StepType;
use App\Mail\CampaignMail;
use App\Models\Audience;
use App\Models\Automation;
use App\Services\AutomationBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

class FieldsTest extends MailroomTestCase
{
    private function withFields(array $attributes = []): Audience
    {
        $audience = $this->audience($attributes);
        $user = $this->admin();
        $this->actingAs($user)->post("/audiences/{$audience->id}/fields", ['label' => 'Company name', 'type' => 'text', 'show_on_form' => 1])->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/audiences/{$audience->id}/fields", ['label' => 'Birthday', 'type' => 'birthday', 'show_on_form' => 1])->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/audiences/{$audience->id}/fields", ['label' => 'Plan', 'type' => 'dropdown', 'options' => "Free\nPro", 'required' => 1, 'show_on_form' => 1]);

        return $audience->fresh();
    }

    public function test_field_keys_are_generated_and_reserved_keys_rejected(): void
    {
        $audience = $this->withFields();
        $this->assertSame(['company_name', 'birthday', 'plan'], $audience->fields->pluck('key')->all());

        $this->actingAs($this->admin())->post("/audiences/{$audience->id}/fields", ['label' => 'X', 'key' => 'email', 'type' => 'text'])->assertSessionHasErrors('key');
        $this->actingAs($this->admin())->post("/audiences/{$audience->id}/fields", ['label' => 'Plan', 'type' => 'text'])->assertSessionHasNoErrors();
        $this->assertSame('plan_2', $audience->fields()->get()->last()->key);
    }

    public function test_signup_form_collects_and_validates_fields_and_gdpr_consent(): void
    {
        Mail::fake();
        $audience = $this->withFields(['double_opt_in' => false]);
        $audience->update(['gdpr_enabled' => true, 'gdpr_text' => 'Yes, email me.']);

        $this->get("/f/{$audience->public_id}")->assertSee('Company name')->assertSee('Yes, email me.');
        $this->post("/f/{$audience->public_id}", ['email' => 'a@x.test', 'fields' => ['plan' => 'Pro']])->assertSessionHasErrors('consent');
        $this->post("/f/{$audience->public_id}", ['email' => 'a@x.test', 'consent' => 1, 'fields' => ['plan' => 'Hacker']])->assertSessionHasErrors('fields.plan');
        $this->post("/f/{$audience->public_id}", ['email' => 'a@x.test', 'consent' => 1, 'fields' => ['birthday' => '13-45', 'plan' => 'Pro']])->assertSessionHasErrors('fields.birthday');

        $this->post("/f/{$audience->public_id}", ['email' => 'a@x.test', 'consent' => 1, 'fields' => ['company_name' => 'Acme', 'birthday' => '04-12', 'plan' => 'Pro', 'evil' => 'x']])->assertOk();
        $contact = $audience->contacts()->sole();
        $this->assertSame(['company_name' => 'Acme', 'birthday' => '04-12', 'plan' => 'Pro'], $contact->fields);
        $this->assertNotNull($contact->consent_at);
        $this->assertSame('Yes, email me.', $contact->consent_text);
        $this->assertSame('127.0.0.1', $contact->signup_ip);
    }

    public function test_merge_tags_segments_import_and_export_use_fields(): void
    {
        $audience = $this->withFields();
        $user = $this->admin();
        $csv = "email,Company name,plan,birthday\nann@x.test,Acme,Pro,02-29\nbob@x.test,=EVIL(),Gold,99-99\n";
        $this->actingAs($user)->post("/audiences/{$audience->id}/import", [
            'file' => UploadedFile::fake()->createWithContent('l.csv', $csv), 'status' => 'subscribed', 'consent' => '1',
        ]);
        $ann = $audience->contacts()->where('email', 'ann@x.test')->sole();
        $bob = $audience->contacts()->where('email', 'bob@x.test')->sole();
        $this->assertSame(['company_name' => 'Acme', 'plan' => 'Pro', 'birthday' => '02-29'], $ann->fields);
        $this->assertSame(['company_name' => '=EVIL()'], $bob->fields); // invalid plan/birthday dropped

        $campaign = $this->campaign($audience, ['html' => '<p>{{ company_name }} / {{ plan | Free }}</p>']);
        (new CampaignMail($campaign, $ann))->assertSeeInHtml('Acme / Pro');
        (new CampaignMail($campaign, $bob))->assertSeeInHtml('=EVIL() / Free');

        $segment = $audience->segments()->create(['name' => 'Pro', 'match' => 'all', 'conditions' => [['field' => 'field:plan', 'operator' => 'is', 'value' => 'Pro']]]);
        $this->assertSame(['ann@x.test'], $segment->contacts()->pluck('email')->all());

        $this->actingAs($user)->post("/audiences/{$audience->id}/segments", ['name' => 'x', 'match' => 'all', 'conditions' => [['field' => 'field:nope', 'operator' => 'is', 'value' => '']]])
            ->assertSessionHasErrors('conditions.0');

        $export = $this->actingAs($user)->get("/audiences/{$audience->id}/export")->streamedContent();
        $this->assertStringContainsString('company_name,birthday,plan', $export);
        $this->assertStringContainsString("'=EVIL()", $export);

        $this->actingAs($user)->get("/campaigns/{$campaign->id}/review")->assertSee('All merge tags are valid.');
    }

    public function test_contact_edit_and_gdpr_export(): void
    {
        $audience = $this->withFields();
        $contact = $this->contact($audience, 'a@x.test', extra: ['fields' => ['company_name' => 'Old', 'plan' => 'Free']]);
        $user = $this->admin();

        $this->actingAs($user)->put("/audiences/{$audience->id}/contacts/{$contact->id}", [
            'email' => 'a@x.test', 'status' => 'subscribed', 'notes' => 'VIP customer', 'fields' => ['company_name' => '', 'plan' => 'Pro'],
        ])->assertSessionHasNoErrors();
        $contact->refresh();
        $this->assertSame(['plan' => 'Pro'], $contact->fields);
        $this->assertSame('VIP customer', $contact->notes);

        $this->actingAs($user)->get("/audiences/{$audience->id}/contacts/{$contact->id}/export")
            ->assertOk()->assertJsonPath('fields.plan', 'Pro')->assertJsonPath('notes', 'VIP customer');
    }

    public function test_birthday_automation_runs_every_year(): void
    {
        Mail::fake();
        $audience = $this->withFields();
        $this->travelTo(now()->setDate(2027, 4, 12)->setTime(8, 0));
        $this->contact($audience, 'bday@x.test', extra: ['fields' => ['birthday' => '04-12', 'plan' => 'Pro']]);
        $this->contact($audience, 'other@x.test', extra: ['fields' => ['birthday' => '05-01', 'plan' => 'Pro']]);

        $this->actingAs($this->admin())->post('/automations', [
            'name' => 'Birthday', 'audience_id' => $audience->id, 'trigger' => 'date', 'trigger_field' => 'company_name', 'preset' => 'blank',
        ])->assertSessionHasErrors('trigger_field');
        $automation = Automation::query()->create(['audience_id' => $audience->id, 'name' => 'Birthday', 'trigger' => 'date', 'trigger_value' => 'birthday', 'status' => 'active']);
        app(AutomationBuilder::class)->add($automation, StepType::Email);

        $this->artisan('automations:date-triggers');
        Mail::assertSent(CampaignMail::class, 1);
        $this->artisan('automations:date-triggers');
        Mail::assertSent(CampaignMail::class, 1);

        $this->travelTo(now()->setDate(2028, 4, 12));
        $this->artisan('automations:date-triggers');
        Mail::assertSent(CampaignMail::class, 2);
    }
}
