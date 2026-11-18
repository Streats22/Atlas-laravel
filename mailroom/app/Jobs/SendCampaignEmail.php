<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ContactStatus;
use App\Mail\CampaignMail;
use App\Models\CampaignSend;
use App\Services\MailConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendCampaignEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 0; // Rate limiting releases the job; failures are capped by maxExceptions.

    public int $maxExceptions = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $sendId) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RateLimited('campaign-mail')];
    }

    public function handle(MailConfig $mailConfig): void
    {
        $mailConfig->apply();

        $send = CampaignSend::query()->with(['campaign', 'contact.audience', 'variant'])->find($this->sendId);

        // Skip already-sent rows (retries) and contacts that unsubscribed after the list was frozen.
        if ($send === null || $send->sent_at !== null || $send->contact->status !== ContactStatus::Subscribed) {
            return;
        }

        try {
            Mail::to($send->contact->email, $send->contact->fullName() ?: null)
                ->send(new CampaignMail($send->variant?->apply($send->campaign) ?? $send->campaign, $send->contact, $send));
            $send->update(['sent_at' => now(), 'failed_at' => null, 'error' => null]);
        } catch (Throwable $e) {
            $send->update(['failed_at' => now(), 'error' => Str::limit($e->getMessage(), 250)]);

            throw $e;
        }
    }
}
