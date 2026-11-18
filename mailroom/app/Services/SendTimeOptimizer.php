<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CampaignSend;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Picks each contact's most common open hour from their history (falling back to the
 * audience's), and delays their email to the next occurrence of that hour within 24 hours.
 */
final class SendTimeOptimizer
{
    /**
     * @param  Collection<int, int>  $contactIds
     * @return array<int, int|null> contact id => hour (0-23) or null when there's no data
     */
    public function bestHours(int $audienceId, Collection $contactIds): array
    {
        $opens = CampaignSend::query()
            ->whereNotNull('opened_at')
            ->whereHas('contact', fn ($q) => $q->where('audience_id', $audienceId))
            ->get(['contact_id', 'opened_at']);

        $audienceHour = $this->mode($opens->map(fn (CampaignSend $s) => (int) $s->opened_at->format('G')));
        $byContact = $opens->groupBy('contact_id');

        return $contactIds->mapWithKeys(fn (int $id) => [
            $id => isset($byContact[$id]) ? $this->mode($byContact[$id]->map(fn (CampaignSend $s) => (int) $s->opened_at->format('G'))) : $audienceHour,
        ])->all();
    }

    public function delayUntil(?int $hour, CarbonInterface $from): ?CarbonInterface
    {
        if ($hour === null) {
            return null;
        }
        $at = $from->copy()->setTime($hour, 0);

        return $at->lessThan($from) ? $at->addDay() : $at;
    }

    /** @param  Collection<int, int>  $hours */
    private function mode(Collection $hours): ?int
    {
        return $hours->isEmpty() ? null : (int) $hours->countBy()->sortDesc()->keys()->first();
    }
}
