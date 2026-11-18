<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignSend;
use Illuminate\Support\Facades\Mail;

class AbTestTest extends MailroomTestCase
{
    private function setup10(array $ab): Campaign
    {
        $audience = $this->audience();
        foreach (range(1, 10) as $i) {
            $this->contact($audience, "c{$i}@x.test");
        }
        $campaign = $this->campaign($audience);
        $this->actingAs($this->admin())->put("/campaigns/{$campaign->id}/ab-test", $ab + [
            'enabled' => 1, 'variable' => 'subject', 'variants' => 2, 'test_percent' => 40, 'winner_by' => 'opens', 'wait_hours' => 4,
            'subject' => ['B' => 'Other subject'],
        ])->assertSessionHasNoErrors();

        return $campaign->fresh();
    }

    public function test_subject_test_sends_to_test_group_then_winner_to_rest(): void
    {
        Mail::fake();
        $campaign = $this->setup10([]);
        $this->assertSame(['A', 'B'], $campaign->variants->pluck('label')->all());

        $this->actingAs($this->admin())->post("/campaigns/{$campaign->id}/send");
        $campaign->refresh();
        $this->assertSame(CampaignStatus::Testing, $campaign->status);
        $this->assertSame(4, CampaignSend::query()->count());
        Mail::assertSent(CampaignMail::class, 4);
        Mail::assertSent(CampaignMail::class, fn (CampaignMail $m) => $m->envelope()->subject === 'Other subject');

        // Variant B gets the opens.
        $b = $campaign->variants()->where('label', 'B')->sole();
        $b->sends()->each(fn (CampaignSend $s) => $s->recordOpen());

        $this->artisan('campaigns:send-scheduled');
        $this->assertSame(CampaignStatus::Testing, $campaign->fresh()->status); // not yet

        $this->travel(5)->hours();
        $this->artisan('campaigns:send-scheduled');

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Sent, $campaign->status);
        $this->assertSame(10, CampaignSend::query()->count());
        $this->assertSame(10, $campaign->recipients_count);
        $this->assertTrue($b->fresh()->is_winner);
        $this->assertSame(6 + 2, $b->sends()->count());
    }

    public function test_manual_winner(): void
    {
        Mail::fake();
        $campaign = $this->setup10(['winner_by' => 'manual']);
        $this->actingAs($this->admin())->post("/campaigns/{$campaign->id}/send");

        $this->travel(10)->days();
        $this->artisan('campaigns:send-scheduled');
        $this->assertSame(CampaignStatus::Testing, $campaign->fresh()->status);

        $a = $campaign->variants()->where('label', 'A')->sole();
        $this->actingAs($this->admin())->get("/campaigns/{$campaign->id}")->assertOk()->assertSee('Send this one');
        $this->actingAs($this->admin())->post("/campaigns/{$campaign->id}/variants/{$a->id}/winner")->assertRedirect();
        $this->assertSame(CampaignStatus::Sent, $campaign->fresh()->status);
        $this->assertSame(8, $a->sends()->count());
    }

    public function test_content_variant_is_edited_and_sent(): void
    {
        Mail::fake();
        $campaign = $this->setup10(['variable' => 'content', 'test_percent' => 100]);
        $b = $campaign->variants()->where('label', 'B')->sole();
        $this->actingAs($this->admin())->get("/campaigns/{$campaign->id}/variants/{$b->id}")->assertOk();
        $this->actingAs($this->admin())->put("/campaigns/{$campaign->id}/variants/{$b->id}", ['html' => '<p>Version B</p>'])->assertRedirect();

        $this->actingAs($this->admin())->post("/campaigns/{$campaign->id}/send");
        Mail::assertSent(CampaignMail::class, 10);
        Mail::assertSent(CampaignMail::class, fn (CampaignMail $m) => str_contains($m->render(), 'Version B'));
    }

    public function test_variant_a_cannot_be_edited_and_missing_values_rejected(): void
    {
        $campaign = $this->setup10([]);
        $a = $campaign->variants()->where('label', 'A')->sole();
        $this->actingAs($this->admin())->get("/campaigns/{$campaign->id}/variants/{$a->id}")->assertNotFound();
        $this->actingAs($this->admin())->put("/campaigns/{$campaign->id}/ab-test", [
            'enabled' => 1, 'variable' => 'subject', 'variants' => 3, 'test_percent' => 20, 'winner_by' => 'opens', 'wait_hours' => 1,
            'subject' => ['B' => 'x', 'C' => ''],
        ])->assertSessionHasErrors('ab');
    }

    public function test_turning_off_removes_variants_and_send_is_normal(): void
    {
        Mail::fake();
        $campaign = $this->setup10([]);
        $this->actingAs($this->admin())->put("/campaigns/{$campaign->id}/ab-test", ['enabled' => 0])->assertRedirect();
        $this->assertSame(0, $campaign->variants()->count());
        $this->actingAs($this->admin())->post("/campaigns/{$campaign->id}/send");
        Mail::assertSent(CampaignMail::class, 10);
    }
}
