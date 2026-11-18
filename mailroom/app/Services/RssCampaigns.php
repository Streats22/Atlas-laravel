<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * RSS-to-email: an "rss" campaign is a template. When it is due and the feed has new items,
 * a regular child campaign is created with {{ rss_items }} filled in and sent to the audience.
 */
final class RssCampaigns
{
    public function __construct(private readonly FeedFetcher $feeds, private readonly CampaignDispatcher $dispatcher) {}

    public function isDue(Campaign $campaign, CarbonImmutable $now): bool
    {
        $rss = $campaign->rss ?? [];
        if ($campaign->status !== CampaignStatus::Scheduled || (int) ($rss['hour'] ?? 9) !== $now->hour) {
            return false;
        }
        if (($rss['frequency'] ?? 'daily') === 'weekly' && (int) ($rss['weekday'] ?? 1) !== $now->dayOfWeekIso) {
            return false;
        }
        $last = isset($rss['last_checked_at']) ? CarbonImmutable::parse($rss['last_checked_at']) : null;

        return $last === null || $last->lt($now->subMinutes(55));
    }

    /** Returns the child campaign that was sent, or null when nothing new. */
    public function run(Campaign $campaign, CarbonImmutable $now): ?Campaign
    {
        $rss = $campaign->rss ?? [];
        try {
            $feed = $this->feeds->fetch((string) ($rss['url'] ?? ''));
        } catch (Throwable $e) {
            $campaign->update(['rss' => $rss + ['last_error' => $e->getMessage()]]);

            return null;
        }

        // First run: only items from the last period, so subscribers don't get the whole archive.
        $since = isset($rss['last_item_at'])
            ? CarbonImmutable::parse($rss['last_item_at'])
            : $now->sub(($rss['frequency'] ?? 'daily') === 'weekly' ? '7 days' : '1 day');
        $new = array_values(array_filter($feed['items'], fn ($i) => $i['date'] !== null && $i['date']->gt($since)));

        $rss['last_checked_at'] = $now->toIso8601String();
        unset($rss['last_error']);
        if ($new === []) {
            $campaign->update(['rss' => $rss]);

            return null;
        }
        $rss['last_item_at'] = collect($new)->max('date')->toIso8601String();
        $campaign->update(['rss' => $rss]);

        $child = $campaign->replicate(['status', 'rss', 'recipients_count', 'sent_at', 'scheduled_at', 'ab_test']);
        $child->fill([
            'kind' => 'regular',
            'parent_id' => $campaign->id,
            'name' => $campaign->name.' · '.$now->format('M j, Y'),
            'subject' => str_replace(['{{ rss_title }}', '{{rss_title}}'], $feed['title'], $campaign->subject),
            'html' => (string) preg_replace('/\{\{\s*rss_items\s*\}\}/', $this->feeds->renderItems($new), $campaign->html),
            'status' => CampaignStatus::Draft,
        ]);
        $child->save();
        $this->dispatcher->dispatch($child);

        return $child->fresh();
    }
}
