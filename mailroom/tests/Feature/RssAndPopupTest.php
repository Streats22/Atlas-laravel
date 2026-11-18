<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Services\FeedFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class RssAndPopupTest extends MailroomTestCase
{
    private function rssXml(string $date): string
    {
        return <<<XML
<?xml version="1.0"?>
<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<rss version="2.0"><channel><title>Acme Blog</title>
<item><title>New post &amp; more</title><link>https://acme.test/new</link><description><![CDATA[<p>Hello <script>x</script><b>world</b></p>]]></description><pubDate>{$date}</pubDate></item>
<item><title>Old post</title><link>javascript:alert(1)</link><description>&xxe;</description><pubDate>Mon, 01 Jan 2024 10:00:00 GMT</pubDate></item>
</channel></rss>
XML;
    }

    public function test_feed_parsing_is_sanitised_and_supports_atom(): void
    {
        $feeds = app(FeedFetcher::class);
        $feed = $feeds->parse($this->rssXml('Tue, 17 Nov 2026 10:00:00 GMT'));
        $this->assertSame('Acme Blog', $feed['title']);
        $this->assertSame('New post & more', $feed['items'][0]['title']);
        $this->assertSame('<p>Hello <b>world</b></p>', $feed['items'][0]['summary']);
        $this->assertSame('', $feed['items'][1]['link']);
        $this->assertStringNotContainsString('root:', $feed['items'][1]['summary']);

        $atom = $feeds->parse('<feed xmlns="http://www.w3.org/2005/Atom"><title>A</title><entry><title>E</title><link href="https://a.test/e"/><updated>2026-11-17T10:00:00Z</updated><summary>S</summary></entry></feed>');
        $this->assertSame('https://a.test/e', $atom['items'][0]['link']);
    }

    public function test_ssrf_guard(): void
    {
        $feeds = app(FeedFetcher::class);
        foreach (['http://127.0.0.1/feed', 'http://169.254.169.254/latest', 'file:///etc/passwd', 'http://10.0.0.5/', 'http://user:pw@1.1.1.1/'] as $url) {
            try {
                $feeds->assertPublicUrl($url);
                $this->fail("{$url} should be rejected");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_rss_campaign_sends_new_items_once(): void
    {
        Mail::fake();
        Http::fake(['93.184.216.34/*' => Http::response($this->rssXml(now()->subHours(3)->toRfc2822String()))]);
        $this->travelTo(now()->startOfWeek()->setTime(9, 0)); // Monday 09:00
        $audience = $this->audience();
        $this->contact($audience, 'a@x.test');
        $user = $this->admin();

        $this->actingAs($user)->post('/campaigns', [
            'kind' => 'rss', 'name' => 'Blog digest', 'audience_id' => $audience->id, 'subject' => 'New from {{ rss_title }}',
            'from_name' => 'A', 'from_email' => 'a@b.test', 'html' => '<div>{{ rss_items }}</div>',
        ]);
        $rss = Campaign::query()->where('kind', 'rss')->sole();
        $this->actingAs($user)->put("/campaigns/{$rss->id}/rss", ['url' => 'http://127.0.0.1/feed', 'frequency' => 'weekly', 'weekday' => 1, 'hour' => 9, 'action' => 'start'])->assertSessionHasErrors('url');
        $this->actingAs($user)->put("/campaigns/{$rss->id}/rss", ['url' => 'http://93.184.216.34/feed', 'frequency' => 'weekly', 'weekday' => 1, 'hour' => 9, 'action' => 'start'])->assertSessionHasNoErrors();
        $this->assertSame(CampaignStatus::Scheduled, $rss->fresh()->status);

        $this->artisan('campaigns:send-scheduled'); // must not blast the template itself
        Mail::assertNothingSent();

        $this->artisan('campaigns:rss');
        Mail::assertSent(CampaignMail::class, 1);
        $issue = Campaign::query()->where('parent_id', $rss->id)->sole();
        $this->assertSame('New from Acme Blog', $issue->subject);
        $this->assertStringContainsString('New post &amp; more', $issue->html);
        $this->assertStringNotContainsString('Old post', $issue->html);

        $this->travel(1)->hours();
        $this->artisan('campaigns:rss'); // wrong hour
        $this->travelTo(now()->addWeek()->startOfWeek()->setTime(9, 0));
        $this->artisan('campaigns:rss'); // same items: nothing new
        $this->assertSame(1, Campaign::query()->where('parent_id', $rss->id)->count());
        $this->actingAs($user)->get("/campaigns/{$rss->id}/edit")->assertSee('Sent issues')->assertSee('Running');
    }

    public function test_popup_script_and_embed_form(): void
    {
        $audience = $this->audience();
        $this->get("/f/{$audience->public_id}/popup.js")->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->assertSee('embed=1', false);
        $this->get("/f/{$audience->public_id}?embed=1")->assertOk()->assertSee('class="public embed"', false);
    }
}
