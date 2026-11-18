<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\CampaignDispatcher;
use Illuminate\Console\Command;

class SendScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:send-scheduled';

    protected $description = 'Send campaigns whose scheduled time has passed';

    public function handle(CampaignDispatcher $dispatcher): int
    {
        Campaign::query()
            ->where('status', CampaignStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->each(function (Campaign $campaign) use ($dispatcher): void {
                $count = $dispatcher->dispatch($campaign);
                $this->info("Queued {$count} emails for \"{$campaign->name}\".");
            });

        return self::SUCCESS;
    }
}
