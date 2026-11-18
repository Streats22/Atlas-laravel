<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Services\Settings;
use App\Services\UserAgent;

class DeliverabilityTest extends MailroomTestCase
{
    private function url(string $provider): string
    {
        app(Settings::class)->put(['webhook_secret' => 'sekret']);

        return "/webhooks/{$provider}/sekret";
    }

    public function test_webhook_requires_the_secret(): void
    {
        $this->url('postmark');
        $this->postJson('/webhooks/postmark/wrong', ['RecordType' => 'Bounce'])->assertNotFound();
        $this->postJson('/webhooks/evil/sekret', [])->assertNotFound();
    }

    public function test_provider_payloads_clean_and_unsubscribe(): void
    {
        $audience = $this->audience();
        $other = $this->audience(['name' => 'Other']);
        $hard = $this->contact($audience, 'hard@x.test');
        $hardOther = $this->contact($other, 'hard@x.test');
        $spam = $this->contact($audience, 'spam@x.test');
        $mg = $this->contact($audience, 'mg@x.test');
        $ses = $this->contact($audience, 'ses@x.test');
        $sg = $this->contact($audience, 'sg@x.test');
        $rs = $this->contact($audience, 'rs@x.test');
        $campaign = $this->campaign($audience);
        $send = $campaign->sends()->create(['contact_id' => $spam->id, 'sent_at' => now()]);

        $this->postJson($this->url('postmark'), ['RecordType' => 'Bounce', 'Type' => 'HardBounce', 'Email' => 'HARD@x.test'])->assertJsonPath('handled', 2);
        $this->postJson($this->url('postmark'), ['RecordType' => 'SpamComplaint', 'Email' => 'spam@x.test'])->assertOk();
        $this->postJson($this->url('mailgun'), ['event-data' => ['event' => 'failed', 'severity' => 'permanent', 'recipient' => 'mg@x.test']])->assertOk();
        $this->postJson($this->url('ses'), ['Type' => 'Notification', 'Message' => json_encode([
            'notificationType' => 'Bounce', 'bounce' => ['bounceType' => 'Permanent', 'bouncedRecipients' => [['emailAddress' => 'ses@x.test']]],
        ])])->assertOk();
        $this->postJson($this->url('sendgrid'), [['event' => 'spamreport', 'email' => 'sg@x.test']])->assertOk();
        $this->postJson($this->url('resend'), ['type' => 'email.bounced', 'data' => ['to' => ['rs@x.test']]])->assertOk();
        $this->postJson($this->url('ses'), ['Type' => 'SubscriptionConfirmation', 'SubscribeURL' => 'http://169.254.169.254/'])->assertOk();

        $this->assertSame(ContactStatus::Cleaned, $hard->fresh()->status);
        $this->assertSame(ContactStatus::Cleaned, $hardOther->fresh()->status);
        $this->assertSame(ContactStatus::Unsubscribed, $spam->fresh()->status);
        $this->assertSame(ContactStatus::Cleaned, $mg->fresh()->status);
        $this->assertSame(ContactStatus::Cleaned, $ses->fresh()->status);
        $this->assertSame(ContactStatus::Unsubscribed, $sg->fresh()->status);
        $this->assertSame(ContactStatus::Cleaned, $rs->fresh()->status);
        $this->assertNotNull($send->fresh()->complained_at);
        $this->assertSame(1, $campaign->stats()['complaints']);
    }

    public function test_three_soft_bounces_clean_an_address(): void
    {
        $contact = $this->contact($this->audience(), 's@x.test');
        $url = $this->url('generic');
        $this->postJson($url, ['type' => 'soft_bounce', 'email' => 's@x.test']);
        $this->postJson($url, ['type' => 'soft_bounce', 'email' => 's@x.test']);
        $this->assertSame(ContactStatus::Subscribed, $contact->fresh()->status);
        $this->postJson($url, ['type' => 'soft_bounce', 'email' => 's@x.test']);
        $this->assertSame(ContactStatus::Cleaned, $contact->fresh()->status);
    }

    public function test_user_agent_classification_and_report(): void
    {
        $ua = app(UserAgent::class);
        $this->assertSame(['client' => 'Gmail', 'device' => 'unknown'], $ua->classify('Mozilla/5.0 (Windows NT 5.1; rv:11.0) Gecko Firefox/11.0 (via ggpht.com GoogleImageProxy)'));
        $this->assertSame(['client' => 'Apple Mail (iOS)', 'device' => 'mobile'], $ua->classify('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148'));
        $this->assertSame('Outlook', $ua->classify('Microsoft Outlook 16.0')['client']);

        $audience = $this->audience();
        $contact = $this->contact($audience, 'a@x.test');
        $campaign = $this->campaign($audience, ['status' => 'sent', 'sent_at' => now()]);
        $send = $campaign->sends()->create(['contact_id' => $contact->id, 'sent_at' => now()]);
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) AppleWebKit/605.1.15 Mobile/15E148')->get("/t/o/{$send->token}.gif");
        $this->assertSame('mobile', $send->fresh()->device);

        $user = $this->admin();
        $this->actingAs($user)->get("/campaigns/{$campaign->id}")->assertSee('Apple Mail (iOS)')->assertSee('Mobile');
        $this->actingAs($user)->get('/reports')->assertOk()->assertSee('Launch')->assertSee('100%');
        $this->assertStringContainsString('Launch', $this->actingAs($user)->get('/reports/export')->streamedContent());
    }

    public function test_contact_rating(): void
    {
        $c = new Contact;
        $this->assertSame(2, $c->rating(0, 0, 0));
        $this->assertSame(1, $c->rating(10, 0, 0));
        $this->assertSame(5, $c->rating(10, 10, 4));
        $this->actingAs($this->admin())->get('/audiences/'.$this->audience()->id)->assertOk()->assertSee('Audience growth');
    }

    public function test_settings_shows_webhook_urls(): void
    {
        $this->actingAs($this->admin())->get('/settings')->assertSee('/webhooks/postmark/', false);
        $this->assertNotNull(app(Settings::class)->get('webhook_secret'));
    }
}
