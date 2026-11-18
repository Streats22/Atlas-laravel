<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AutomationTrigger;
use App\Enums\ContactStatus;
use App\Enums\StepType;
use App\Jobs\SendCampaignEmail;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\Contact;

/**
 * Enrols contacts into automations and walks them through the steps.
 * Wait steps park a run until next_run_at; `automations:run` (every minute) picks it up again.
 */
final class AutomationEngine
{
    public function onSubscribed(Contact $contact): void
    {
        $this->trigger($contact, AutomationTrigger::Subscribed);
    }

    public function onTagAdded(Contact $contact, string $tag): void
    {
        $this->trigger($contact, AutomationTrigger::TagAdded, $tag);
    }

    public function enroll(Automation $automation, Contact $contact): ?AutomationRun
    {
        if (! $automation->isActive() || $contact->audience_id !== $automation->audience_id || $contact->status !== ContactStatus::Subscribed) {
            return null;
        }

        $run = $automation->runs()->firstOrCreate(['contact_id' => $contact->id], ['next_run_at' => now(), 'status' => 'active', 'position' => 0]);
        if ($run->wasRecentlyCreated) {
            $this->process($run);
        }

        return $run;
    }

    public function processDue(): int
    {
        $count = 0;
        AutomationRun::query()
            ->where('status', 'active')
            ->where('next_run_at', '<=', now())
            ->whereHas('automation', fn ($q) => $q->where('status', 'active'))
            ->with(['automation.steps.campaign', 'contact'])
            ->chunkById(200, function ($runs) use (&$count): void {
                foreach ($runs as $run) {
                    $this->process($run);
                    $count++;
                }
            });

        return $count;
    }

    public function process(AutomationRun $run): void
    {
        $run->loadMissing(['automation.steps.campaign', 'contact']);
        $steps = $run->automation->steps->values();

        while ($run->status === 'active') {
            if ($run->contact->status !== ContactStatus::Subscribed) {
                $run->update(['status' => 'exited', 'next_run_at' => null]);

                return;
            }

            $step = $steps[$run->position] ?? null;
            if ($step === null) {
                $run->update(['status' => 'completed', 'completed_at' => now(), 'next_run_at' => null]);

                return;
            }

            $run->position++;
            match ($step->type) {
                StepType::Email => $this->sendEmail($step->campaign, $run->contact),
                StepType::Tag => app(TagSync::class)->attach($run->contact, (string) ($step->config['tag'] ?? '')),
                StepType::Untag => app(TagSync::class)->detach($run->contact, (string) ($step->config['tag'] ?? '')),
                StepType::Wait => null,
            };

            if ($step->type === StepType::Wait && $step->delayInSeconds() > 0) {
                $run->next_run_at = now()->addSeconds($step->delayInSeconds());
                $run->save();

                return;
            }
            $run->save();
        }
    }

    private function trigger(Contact $contact, AutomationTrigger $trigger, ?string $value = null): void
    {
        Automation::query()
            ->where('audience_id', $contact->audience_id)
            ->where('status', 'active')
            ->where('trigger', $trigger)
            ->when($value !== null, fn ($q) => $q->where('trigger_value', $value))
            ->get()
            ->each(fn (Automation $automation) => $this->enroll($automation, $contact));
    }

    private function sendEmail(?Campaign $campaign, Contact $contact): void
    {
        if ($campaign === null || trim($campaign->html) === '') {
            return;
        }
        $send = $campaign->sends()->firstOrCreate(['contact_id' => $contact->id]);
        if ($send->wasRecentlyCreated) {
            $campaign->increment('recipients_count');
            SendCampaignEmail::dispatch($send->id)->afterCommit();
        }
    }
}
