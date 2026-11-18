<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\RssCampaigns;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendRssCampaigns extends Command
{
    protected $signature = 'campaigns:rss';

    protected $description = 'Check RSS campaigns that are due and send new feed items';

    public function handle(RssCampaigns $rss): int
    {
        $now = CarbonImmutable::now();
        Campaign::query()->where('kind', 'rss')->where('status', CampaignStatus::Scheduled)->get()
            ->filter(fn (Campaign $c) => $rss->isDue($c, $now))
            ->each(function (Campaign $campaign) use ($rss, $now): void {
                $child = $rss->run($campaign, $now);
                $this->info($child ? "Sent \"{$child->name}\" to {$child->recipients_count} recipients." : "No new items for \"{$campaign->name}\".");
            });

        return self::SUCCESS;
    }
}
