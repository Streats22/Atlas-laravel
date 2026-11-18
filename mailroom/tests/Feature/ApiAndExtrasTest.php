<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\CampaignMail;
use App\Mail\ConfirmSubscriptionMail;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ApiAndExtrasTest extends MailroomTestCase
{
    private function key(Role $role = Role::Manager): string
    {
        return ApiKey::issue(User::factory()->create(['role' => $role]), 'test');
    }

    public function test_api_requires_a_valid_key_and_respects_roles(): void
    {
        $this->getJson('/api/v1/ping')->assertUnauthorized();
        $this->withToken('mr_wrong')->getJson('/api/v1/ping')->assertUnauthorized();

        $viewer = $this->key(Role::Viewer);
        $audience = $this->audience();
        $this->withToken($viewer)->getJson('/api/v1/ping')->assertOk()->assertJsonPath('role', 'viewer');
        $this->withToken($viewer)->putJson("/api/v1/audiences/{$audience->id}/contacts", ['email' => 'a@x.test'])->assertForbidden();
        $this->assertSame(1, ApiKey::query()->count());
        $this->assertStringNotContainsString($viewer, (string) ApiKey::query()->value('token_hash'));
    }

    public function test_contact_upsert_tags_and_delete(): void
    {
        Mail::fake();
        $audience = $this->audience();
        $audience->fields()->create(['label' => 'Plan', 'key' => 'plan', 'type' => 'dropdown', 'options' => ['Free', 'Pro']]);
        $key = $this->key();
        $url = "/api/v1/audiences/{$audience->id}/contacts";

        $this->withToken($key)->putJson($url, ['email' => 'A@X.test', 'first_name' => 'Ann', 'fields' => ['plan' => 'Gold']])->assertUnprocessable();
        $this->withToken($key)->putJson($url, ['email' => 'A@X.test', 'first_name' => 'Ann', 'fields' => ['plan' => 'Pro'], 'tags' => ['vip']])
            ->assertCreated()->assertJsonPath('data.email', 'a@x.test')->assertJsonPath('data.fields.plan', 'Pro')->assertJsonPath('data.tags.0', 'vip');

        $audience->contacts()->sole()->unsubscribe();
        $this->withToken($key)->putJson($url, ['email' => 'a@x.test', 'last_name' => 'Lee'])->assertOk()->assertJsonPath('data.status', 'unsubscribed');

        $this->withToken($key)->putJson($url, ['email' => 'new@x.test', 'status_if_new' => 'pending'])->assertCreated();
        Mail::assertSent(ConfirmSubscriptionMail::class, 1);

        $this->withToken($key)->postJson("{$url}/a@x.test/tags", ['add' => ['x'], 'remove' => ['vip']])->assertJsonPath('data.tags', ['x']);
        $this->withToken($key)->getJson("{$url}?status=pending")->assertJsonPath('meta.total', 1);
        $this->withToken($key)->deleteJson("{$url}/a@x.test")->assertNoContent();
        $this->withToken($key)->getJson("{$url}/a@x.test")->assertNotFound();
    }

    public function test_campaign_report_and_transactional_send(): void
    {
        Mail::fake();
        $campaign = $this->campaign($this->audience(), ['status' => 'sent']);
        $key = $this->key();
        $this->withToken($key)->getJson('/api/v1/campaigns')->assertJsonPath('data.0.id', $campaign->id);
        $this->withToken($key)->getJson("/api/v1/campaigns/{$campaign->id}/report")->assertJsonPath('data.open_rate', 0);
        $this->withToken($key)->postJson('/api/v1/messages/send', ['to' => 'x@y.test', 'subject' => 'Receipt', 'html' => '<p>Thanks</p>'])->assertAccepted();
    }

    public function test_web_version_archive_and_hash_protection(): void
    {
        $audience = $this->audience();
        $contact = $this->contact($audience, 'a@x.test', extra: ['first_name' => 'Ann']);
        $campaign = $this->campaign($audience, ['status' => 'sent', 'sent_at' => now()]);
        $send = $campaign->sends()->create(['contact_id' => $contact->id, 'sent_at' => now()]);

        $this->assertStringContainsString(route('public.web', $send->token), (new CampaignMail($campaign, $contact, $send))->render());
        $this->get("/w/{$send->token}")->assertOk()->assertSee('Hello Ann');
        $this->get($campaign->webUrl())->assertOk()->assertDontSee('Ann');
        $this->get("/c/{$campaign->id}/wronghash")->assertNotFound();
        $draft = $this->campaign($audience);
        $this->get($draft->webUrl())->assertNotFound();
        $this->get("/archive/{$audience->public_id}")->assertOk()->assertSee('Hi {{ first_name | friend }}', false);
    }

    public function test_resend_to_non_openers(): void
    {
        Mail::fake();
        $audience = $this->audience();
        $opener = $this->contact($audience, 'o@x.test');
        $silent = $this->contact($audience, 's@x.test');
        $this->contact($audience, 'late@x.test'); // joined later, never received it
        $campaign = $this->campaign($audience, ['status' => 'sent', 'sent_at' => now()]);
        $campaign->sends()->create(['contact_id' => $opener->id, 'sent_at' => now(), 'opened_at' => now()]);
        $campaign->sends()->create(['contact_id' => $silent->id, 'sent_at' => now()]);

        $this->actingAs($this->admin())->post("/campaigns/{$campaign->id}/resend")->assertRedirect();
        $copy = Campaign::query()->where('resend_of_id', $campaign->id)->sole();
        $this->assertSame(['s@x.test'], $copy->recipients()->pluck('email')->all());
    }
}
