<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Jobs\SendCampaignEmail;
use App\Models\Campaign;
use App\Models\CampaignVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A/B tests: a share of the recipients is split over the variants; after the wait time the
 * best variant (by opens or clicks, or picked by hand) goes to everyone else.
 */
final class AbTest
{
    public const VARIABLES = ['subject' => 'Subject line', 'from_name' => 'From name', 'content' => 'Content', 'send_time' => 'Send time'];

    public const LABELS = ['A', 'B', 'C'];

    /** @param  array<string, mixed>  $data  validated settings */
    public function configure(Campaign $campaign, array $data): void
    {
        if (empty($data['enabled'])) {
            $campaign->update(['ab_test' => null]);
            $campaign->variants()->delete();

            return;
        }

        $count = (int) $data['variants'];
        $campaign->update(['ab_test' => [
            'enabled' => true,
            'variable' => $data['variable'],
            'variants' => $count,
            'test_percent' => (int) $data['test_percent'],
            'winner_by' => $data['winner_by'],
            'wait_hours' => (int) $data['wait_hours'],
        ]]);

        $campaign->variants()->whereNotIn('label', array_slice(self::LABELS, 0, $count))->delete();
        foreach (array_slice(self::LABELS, 0, $count) as $i => $label) {
            $variant = $campaign->variants()->firstOrNew(['label' => $label]);
            // Variant A is the campaign itself; B and C hold overrides for the tested variable only.
            $variant->fill([
                'subject' => $label !== 'A' && $data['variable'] === 'subject' ? ($data['subject'][$label] ?? null) : null,
                'from_name' => $label !== 'A' && $data['variable'] === 'from_name' ? ($data['from_name'][$label] ?? null) : null,
                'send_delay_hours' => $data['variable'] === 'send_time' ? (int) ($data['send_delay'][$label] ?? $i * 2) : 0,
            ]);
            if ($data['variable'] !== 'content') {
                $variant->html = null;
                $variant->design = null;
            } elseif ($label !== 'A' && $variant->html === null) {
                $variant->html = $campaign->html;
                $variant->design = $campaign->design;
            }
            $variant->save();
        }
    }

    /** Called by the dispatcher once the campaign is claimed. Returns the number of test emails queued. */
    public function start(Campaign $campaign): int
    {
        $settings = $campaign->ab_test;
        $variants = $campaign->variants()->get()->values();
        $ids = $campaign->recipients()->pluck('contacts.id')->shuffle()->values();
        $testSize = min($ids->count(), max($variants->count(), (int) ceil($ids->count() * $settings['test_percent'] / 100)));

        $this->queue($campaign, $ids->take($testSize), fn (int $i) => $variants[$i % $variants->count()]);

        $maxDelay = (int) $variants->max('send_delay_hours');
        $campaign->update([
            'status' => CampaignStatus::Testing,
            'sent_at' => now(),
            'recipients_count' => $testSize,
            'ab_test' => $settings + ['decide_at' => now()->addHours($settings['wait_hours'] + $maxDelay)->toIso8601String(), 'total' => $ids->count()],
        ]);

        return $testSize;
    }

    public function winner(Campaign $campaign): CampaignVariant
    {
        $metric = ($campaign->ab_test['winner_by'] ?? 'opens') === 'clicks' ? 'click_rate' : 'open_rate';

        // Highest rate wins; ties go to the earliest label (A), so results are deterministic.
        return $campaign->variants()->get()
            ->sortBy([fn ($a, $b) => $b->stats()[$metric] <=> $a->stats()[$metric], fn ($a, $b) => $a->label <=> $b->label])
            ->first();
    }

    /** Send the winning (or chosen) variant to everyone who wasn't in the test group. */
    public function finish(Campaign $campaign, ?CampaignVariant $choice = null): int
    {
        $claimed = Campaign::query()->whereKey($campaign->id)->where('status', CampaignStatus::Testing)->update(['status' => CampaignStatus::Sending]);
        if ($claimed === 0) {
            return 0;
        }
        $campaign->refresh();
        $winner = $choice ?? $this->winner($campaign);
        $winner->update(['is_winner' => true]);

        $remaining = $campaign->recipients()->whereDoesntHave('sends', fn ($q) => $q->where('campaign_id', $campaign->id))->pluck('contacts.id');
        // The winning send time is "now": the decision point is already past the tested delays.
        $this->queue($campaign, $remaining, fn () => $winner, ignoreDelay: true);

        $campaign->update([
            'status' => CampaignStatus::Sent,
            'recipients_count' => $campaign->recipients_count + $remaining->count(),
        ]);

        return $remaining->count();
    }

    /** @param  Collection<int, int>  $contactIds */
    private function queue(Campaign $campaign, Collection $contactIds, callable $variantFor, bool $ignoreDelay = false): void
    {
        foreach ($contactIds->chunk(500) as $chunk) {
            DB::transaction(function () use ($campaign, $chunk, $variantFor, $ignoreDelay): void {
                foreach ($chunk->values() as $i => $contactId) {
                    /** @var CampaignVariant $variant */
                    $variant = $variantFor($i);
                    $send = $campaign->sends()->firstOrCreate(['contact_id' => $contactId], ['variant_id' => $variant->id]);
                    $job = SendCampaignEmail::dispatch($send->id)->afterCommit();
                    if (! $ignoreDelay && $variant->send_delay_hours > 0) {
                        $job->delay(now()->addHours($variant->send_delay_hours));
                    }
                }
            });
        }
    }
}
