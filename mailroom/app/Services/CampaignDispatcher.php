<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Jobs\SendCampaignEmail;
use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

final class CampaignDispatcher
{
    /** Freeze the recipient list and queue one job per recipient. Returns the number queued. */
    public function dispatch(Campaign $campaign): int
    {
        $claimed = Campaign::query()
            ->whereKey($campaign->id)
            ->where('kind', 'regular')
            ->whereIn('status', [CampaignStatus::Draft, CampaignStatus::Scheduled])
            ->update(['status' => CampaignStatus::Sending]);

        if ($claimed === 0) {
            return 0; // Already being sent by another worker / request.
        }

        $campaign->refresh();
        if ($campaign->hasAbTest() && $campaign->variants()->count() >= 2) {
            return app(AbTest::class)->start($campaign);
        }
        $count = 0;
        $optimizer = app(SendTimeOptimizer::class);
        $start = now();

        $campaign->recipients()->select('contacts.id')->chunkById(500, function ($contacts) use ($campaign, &$count, $optimizer, $start): void {
            $hours = $campaign->optimize_send_time ? $optimizer->bestHours($campaign->audience_id, $contacts->pluck('id')) : [];
            DB::transaction(function () use ($contacts, $campaign, &$count, $hours, $optimizer, $start): void {
                foreach ($contacts as $contact) {
                    /** @var Contact $contact */
                    $send = $campaign->sends()->firstOrCreate(['contact_id' => $contact->id]);
                    $job = SendCampaignEmail::dispatch($send->id)->afterCommit();
                    if ($at = $optimizer->delayUntil($hours[$contact->id] ?? null, $start)) {
                        $job->delay($at);
                    }
                    $count++;
                }
            });
        }, 'contacts.id', 'id');

        $campaign->update([
            'recipients_count' => $count,
            'sent_at' => now(),
            'status' => CampaignStatus::Sent,
        ]);

        return $count;
    }
}
