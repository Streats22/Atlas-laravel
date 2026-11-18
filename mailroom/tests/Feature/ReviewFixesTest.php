<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\CampaignMail;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\FeedFetcher;
use App\Services\RssCampaigns;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ReviewFixesTest extends MailroomTestCase
{
    public function test_authors_cannot_edit_scheduled_campaigns(): void
    {
        $campaign = $this->campaign($this->audience(), ['status' => 'scheduled', 'scheduled_at' => now()->addDay()]);
        $author = User::factory()->create(['role' => Role::Author]);
        $payload = ['name' => 'X'] + $campaign->only(['audience_id', 'subject', 'from_name', 'from_email', 'html']);

        $this->actingAs($author)->put("/campaigns/{$campaign->id}", $payload)->assertForbidden();
        $this->actingAs($author)->put("/campaigns/{$campaign->id}/ab-test", ['enabled' => 0])->assertForbidden();
        $this->actingAs($this->admin())->put("/campaigns/{$campaign->id}", $payload)->assertRedirect();
    }

    public function test_segment_used_by_unsent_campaign_cannot_be_deleted(): void
    {
        $audience = $this->audience();
        $segment = $audience->segments()->create(['name' => 'S', 'match' => 'all', 'conditions' => [['field' => 'email', 'operator' => 'contains', 'value' => 'a']]]);
        $this->campaign($audience, ['segment_id' => $segment->id, 'status' => 'scheduled']);

        $this->actingAs($this->admin())->delete("/audiences/{$audience->id}/segments/{$segment->id}")->assertSessionHasErrors('segment');
        $this->assertNotNull($segment->fresh());
    }

    public function test_previews_and_tests_never_leak_real_tokens(): void
    {
        Mail::fake();
        $audience = $this->audience();
        $contact = $this->contact($audience, 'a@x.test');
        $campaign = $this->campaign($audience);
        $user = $this->admin();

        $this->actingAs($user)->get("/campaigns/{$campaign->id}/preview?contact={$contact->id}")->assertDontSee($contact->token, false);
        $this->actingAs($user)->post("/campaigns/{$campaign->id}/test", ['emails' => 'a@x.test']);
        Mail::assertSent(CampaignMail::class, fn ($m) => ! str_contains($m->render(), $contact->token));

        $author = User::factory()->create(['role' => Role::Author]);
        $this->actingAs($author)->post("/campaigns/{$campaign->id}/test", ['emails' => 'stranger@x.test'])->assertSessionHasErrors('emails');
    }

    public function test_viewer_api_key_cannot_bulk_list_contacts(): void
    {
        $audience = $this->audience();
        $key = ApiKey::issue(User::factory()->create(['role' => Role::Viewer]), 'k');
        $this->withToken($key)->getJson("/api/v1/audiences/{$audience->id}/contacts")->assertForbidden();
    }

    public function test_ssrf_guard_blocks_cgnat_ipv6_local_and_mapped(): void
    {
        $feeds = app(FeedFetcher::class);
        foreach (['http://100.64.1.1/', 'http://[::1]/', 'http://[fd00::1]/', 'http://[::ffff:127.0.0.1]/'] as $url) {
            try {
                $feeds->assertPublicUrl($url);
                $this->fail("{$url} should be rejected");
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_rss_keeps_dollar_signs_and_sends_undated_items_once(): void
    {
        Mail::fake();
        $xml = '<rss><channel><title>T</title><item><title>Deal</title><link>https://a.test/1</link><description>Now only $10 \1</description></item></channel></rss>';
        Http::fake(['*' => Http::response($xml)]);
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test');
        $rss = $this->campaign($audience, ['kind' => 'rss', 'status' => 'scheduled', 'html' => '{{ rss_items }}', 'rss' => ['url' => 'http://93.184.216.34/f', 'frequency' => 'daily', 'hour' => 9]]);

        $child = app(RssCampaigns::class)->run($rss, CarbonImmutable::now());
        $this->assertNotNull($child);
        $this->assertStringContainsString('Now only $10 \1', $child->html);
        $this->assertNull(app(RssCampaigns::class)->run($rss->fresh(), CarbonImmutable::now()->addDay()));
    }
}
