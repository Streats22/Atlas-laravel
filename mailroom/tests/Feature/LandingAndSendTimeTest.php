<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Builder\EmailDesign;
use App\Jobs\SendCampaignEmail;
use App\Models\CampaignSend;
use App\Models\LandingPage;
use App\Services\CampaignDispatcher;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class LandingAndSendTimeTest extends MailroomTestCase
{
    public function test_landing_page_lifecycle_counts_visits_and_signups(): void
    {
        Mail::fake();
        $audience = $this->audience(['double_opt_in' => false]);
        $user = $this->admin();

        $this->actingAs($user)->post('/landing-pages', ['title' => 'Launch Week!', 'audience_id' => $audience->id])->assertRedirect();
        $page = LandingPage::query()->sole();
        $this->assertSame('launch-week', $page->slug);
        $this->assertStringContainsString('name="landing" value="'.$page->id.'"', $page->html);

        $this->get('/p/launch-week')->assertNotFound(); // draft
        $this->actingAs($user)->get("/landing-pages/{$page->id}/preview")->assertOk();

        $design = $page->design;
        $design['blocks'][] = ['type' => 'text', 'props' => ['html' => '<p>Hi<script>x</script></p>']];
        $this->actingAs($user)->put("/landing-pages/{$page->id}", [
            'title' => 'Launch', 'slug' => 'launch', 'meta_description' => 'Our launch', 'tag' => 'launch', 'published' => 1, 'design' => json_encode($design),
        ])->assertSessionHasNoErrors();

        auth()->logout();
        $this->get('/p/launch')->assertOk()->assertSee('<title>Launch</title>', false)->assertDontSee('<script>x', false)
            ->assertHeader('Content-Security-Policy', "script-src 'none'; frame-ancestors 'self'");
        $this->post("/f/{$audience->public_id}", ['email' => 'lp@x.test', 'landing' => $page->id])->assertOk();

        $page->refresh();
        $this->assertSame(1, $page->visits);
        $this->assertSame(1, $page->signups);
        $this->assertSame(['launch'], $audience->contacts()->sole()->tags->pluck('name')->all());
    }

    public function test_slug_rules_and_signup_block_only_on_pages(): void
    {
        $audience = $this->audience();
        $this->actingAs($this->admin())->post('/landing-pages', ['title' => 'A', 'audience_id' => $audience->id]);
        $page = LandingPage::query()->sole();
        $this->actingAs($this->admin())->put("/landing-pages/{$page->id}", ['title' => 'A', 'slug' => '../etc', 'design' => '{}'])->assertSessionHasErrors('slug');

        $email = app(EmailDesign::class)->normalize(['blocks' => [['type' => 'signup']]]);
        $this->assertSame([], $email['blocks']);
    }

    public function test_send_time_optimisation_delays_to_each_contacts_open_hour(): void
    {
        Queue::fake();
        $this->travelTo(now()->setTime(10, 0));
        $audience = $this->audience();
        $morning = $this->contact($audience, 'morning@x.test');
        $evening = $this->contact($audience, 'evening@x.test');
        $new = $this->contact($audience, 'new@x.test');
        $old = $this->campaign($audience, ['status' => 'sent']);
        $old->sends()->create(['contact_id' => $morning->id, 'opened_at' => now()->setTime(7, 15)]);
        $old->sends()->create(['contact_id' => $evening->id, 'opened_at' => now()->setTime(20, 5)]);
        $other = $this->campaign($audience, ['status' => 'sent']);
        $other->sends()->create(['contact_id' => $evening->id, 'opened_at' => now()->setTime(20, 30)]);

        $campaign = $this->campaign($audience, ['optimize_send_time' => true]);
        app(CampaignDispatcher::class)->dispatch($campaign);

        $delays = [];
        Queue::assertPushed(SendCampaignEmail::class, function (SendCampaignEmail $job) use (&$delays) {
            $send = CampaignSend::query()->find($job->sendId);
            $delays[$send->contact->email] = $job->delay?->format('Y-m-d H:i');

            return true;
        });
        $this->assertSame(now()->addDay()->setTime(7, 0)->format('Y-m-d H:i'), $delays['morning@x.test']);
        $this->assertSame(now()->setTime(20, 0)->format('Y-m-d H:i'), $delays['evening@x.test']);
        $this->assertNotNull($delays['new@x.test']); // audience-wide best hour
    }
}
