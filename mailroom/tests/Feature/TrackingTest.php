<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CampaignMail;

class TrackingTest extends MailroomTestCase
{
    public function test_open_pixel_and_click_redirect_are_recorded(): void
    {
        $audience = $this->audience();
        $contact = $this->contact($audience, 'a@x.test');
        $campaign = $this->campaign($audience);
        $send = $campaign->sends()->create(['contact_id' => $contact->id, 'sent_at' => now()]);
        (new CampaignMail($campaign, $contact, $send))->render();
        $link = $campaign->links()->sole();

        $this->get("/t/o/{$send->token}.gif")->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get("/t/c/{$send->token}/{$link->hash}")->assertRedirect('https://acme.test/offer');

        $send->refresh();
        $this->assertSame(1, $send->open_count);
        $this->assertSame(1, $send->click_count);
        $this->assertSame(1, $link->fresh()->click_count);
        $this->assertSame(100.0, $campaign->stats()['click_rate']);
    }

    public function test_click_endpoint_is_not_an_open_redirect(): void
    {
        $audience = $this->audience();
        $other = $this->campaign($audience, ['html' => '<a href="https://evil.test">x</a>']);
        $contact = $this->contact($audience, 'a@x.test');
        $mine = $this->campaign($audience);
        $send = $mine->sends()->create(['contact_id' => $contact->id]);
        $otherLink = $other->links()->create(['hash' => 'abcdabcdabcdabcd', 'url' => 'https://evil.test']);

        $this->get("/t/c/{$send->token}/{$otherLink->hash}")->assertNotFound();
        $this->get('/t/c/bad-token/abcdabcdabcdabcd')->assertNotFound();
    }

    public function test_unsubscribe_via_campaign_is_attributed(): void
    {
        $audience = $this->audience();
        $contact = $this->contact($audience, 'a@x.test');
        $campaign = $this->campaign($audience);
        $send = $campaign->sends()->create(['contact_id' => $contact->id, 'sent_at' => now()]);

        $this->post("/u/{$contact->token}?send={$send->token}")->assertOk();
        $this->assertSame(1, $campaign->stats()['unsubscribes']);
    }
}
