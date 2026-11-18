<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\AbTest;
use App\Services\CampaignDispatcher;
use Illuminate\Console\Command;

class SendScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:send-scheduled';

    protected $description = 'Send scheduled campaigns and finish A/B tests whose wait time has passed';

    public function handle(CampaignDispatcher $dispatcher, AbTest $abTest): int
    {
        Campaign::query()
            ->where('status', CampaignStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->each(function (Campaign $campaign) use ($dispatcher): void {
                $count = $dispatcher->dispatch($campaign);
                $this->info("Queued {$count} emails for \"{$campaign->name}\".");
            });

        // A/B tests whose wait time is over: automatic winners go out to the rest of the list.
        Campaign::query()->where('status', CampaignStatus::Testing)->get()
            ->filter(fn (Campaign $c) => ($c->ab_test['winner_by'] ?? '') !== 'manual' && now()->gte($c->ab_test['decide_at'] ?? now()))
            ->each(function (Campaign $campaign) use ($abTest): void {
                $count = $abTest->finish($campaign);
                $this->info("A/B winner for \"{$campaign->name}\" sent to {$count} more recipients.");
            });

        return self::SUCCESS;
    }
}
