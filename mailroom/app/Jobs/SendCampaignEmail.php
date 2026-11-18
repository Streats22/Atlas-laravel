<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ContactStatus;
use App\Mail\CampaignMail;
use App\Models\CampaignSend;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendCampaignEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $sendId) {}

    public function handle(): void
    {
        $send = CampaignSend::query()->with(['campaign', 'contact.audience'])->find($this->sendId);

        // Skip already-sent rows (retries) and contacts that unsubscribed after the list was frozen.
        if ($send === null || $send->sent_at !== null || $send->contact->status !== ContactStatus::Subscribed) {
            return;
        }

        try {
            Mail::to($send->contact->email, $send->contact->fullName() ?: null)
                ->send(new CampaignMail($send->campaign, $send->contact, $send));
            $send->update(['sent_at' => now(), 'failed_at' => null, 'error' => null]);
        } catch (Throwable $e) {
            $send->update(['failed_at' => now(), 'error' => Str::limit($e->getMessage(), 250)]);

            throw $e;
        }
    }
}
