<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\ContactStatus;
use App\Mail\CampaignMail;
use App\Models\CampaignSend;
use App\Services\CampaignDispatcher;
use App\Services\TagSync;
use Illuminate\Support\Facades\Mail;

class CampaignSendingTest extends MailroomTestCase
{
    public function test_send_now_mails_only_subscribed_contacts(): void
    {
        Mail::fake();
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test', extra: ['first_name' => 'Ann']);
        $this->contact($audience, 'b@x.test');
        $this->contact($audience, 'gone@x.test', ContactStatus::Unsubscribed);
        $campaign = $this->campaign($audience);

        $this->actingAs($this->admin())->post("/campaigns/{$campaign->id}/send")->assertRedirect("/campaigns/{$campaign->id}");

        Mail::assertSent(CampaignMail::class, 2);
        $this->assertSame(CampaignStatus::Sent, $campaign->fresh()->status);
        $this->assertSame(2, $campaign->fresh()->recipients_count);
        $this->assertSame(2, CampaignSend::query()->whereNotNull('sent_at')->count());
    }

    public function test_campaign_cannot_be_sent_twice(): void
    {
        Mail::fake();
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test');
        $campaign = $this->campaign($audience);
        $dispatcher = app(CampaignDispatcher::class);

        $this->assertSame(1, $dispatcher->dispatch($campaign));
        $this->assertSame(0, $dispatcher->dispatch($campaign->fresh()));
        Mail::assertSent(CampaignMail::class, 1);
    }

    public function test_tag_targeting_limits_recipients(): void
    {
        $audience = $this->audience();
        $vip = $this->contact($audience, 'vip@x.test');
        $this->contact($audience, 'normal@x.test');
        app(TagSync::class)->attach($vip, 'vip');
        $campaign = $this->campaign($audience, ['tag_id' => $audience->tags()->sole()->id]);

        $this->assertSame(['vip@x.test'], $campaign->recipients()->pluck('email')->all());
    }

    public function test_rendered_email_personalises_escapes_and_tracks(): void
    {
        $audience = $this->audience();
        $contact = $this->contact($audience, 'a@x.test', extra: ['first_name' => '<script>x</script>']);
        $campaign = $this->campaign($audience);
        $send = $campaign->sends()->create(['contact_id' => $contact->id]);

        $html = (new CampaignMail($campaign, $contact, $send))->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>x', $html);
        $this->assertStringNotContainsString('href="https://acme.test/offer"', $html);
        $this->assertStringContainsString(route('track.click', ['token' => $send->token, 'hash' => $campaign->links()->sole()->hash]), $html);
        $this->assertStringContainsString(route('track.open', $send->token), $html);
        $this->assertStringContainsString('/u/'.$contact->token, $html);
    }

    public function test_subject_merge_tag_uses_fallback(): void
    {
        $audience = $this->audience();
        $contact = $this->contact($audience, 'a@x.test');
        $mail = new CampaignMail($this->campaign($audience), $contact, null);
        $this->assertSame('[Test] Hi friend', $mail->envelope()->subject);
    }

    public function test_scheduled_campaigns_are_sent_by_the_command(): void
    {
        Mail::fake();
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test');
        $due = $this->campaign($audience, ['status' => CampaignStatus::Scheduled, 'scheduled_at' => now()->subMinute()]);
        $later = $this->campaign($audience, ['status' => CampaignStatus::Scheduled, 'scheduled_at' => now()->addDay()]);

        $this->artisan('campaigns:send-scheduled')->assertSuccessful();

        $this->assertSame(CampaignStatus::Sent, $due->fresh()->status);
        $this->assertSame(CampaignStatus::Scheduled, $later->fresh()->status);
    }

    public function test_sent_campaign_cannot_be_edited(): void
    {
        $campaign = $this->campaign($this->audience(), ['status' => CampaignStatus::Sent]);
        $this->actingAs($this->admin())
            ->put("/campaigns/{$campaign->id}", ['name' => 'x'] + $campaign->toArray())
            ->assertStatus(409);
    }
}
