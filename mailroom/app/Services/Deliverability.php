<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactStatus;
use App\Models\CampaignSend;
use App\Models\Contact;
use Illuminate\Support\Collection;

/**
 * Applies bounce and complaint events: hard bounces and repeated soft bounces clean the
 * address, complaints unsubscribe it. Events are matched by email across all audiences.
 */
final class Deliverability
{
    public const SOFT_BOUNCE_LIMIT = 3;

    public function bounce(string $email, bool $hard, ?string $sendToken = null): int
    {
        $contacts = $this->contacts($email);
        foreach ($contacts as $contact) {
            $this->markSend($contact, $sendToken, ['bounced_at' => now(), 'bounce_type' => $hard ? 'hard' : 'soft']);
            if ($hard || $contact->soft_bounces + 1 >= self::SOFT_BOUNCE_LIMIT) {
                $contact->update(['status' => ContactStatus::Cleaned, 'soft_bounces' => $contact->soft_bounces + ($hard ? 0 : 1)]);
            } else {
                $contact->increment('soft_bounces');
            }
        }

        return $contacts->count();
    }

    public function complaint(string $email, ?string $sendToken = null): int
    {
        $contacts = $this->contacts($email);
        foreach ($contacts as $contact) {
            $this->markSend($contact, $sendToken, ['complained_at' => now(), 'unsubscribed_at' => now()]);
            if ($contact->status !== ContactStatus::Unsubscribed) {
                $contact->unsubscribe();
            }
        }

        return $contacts->count();
    }

    /** @return Collection<int, Contact> */
    private function contacts(string $email): Collection
    {
        $email = strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? Contact::query()->where('email', $email)->get() : collect();
    }

    /** @param  array<string, mixed>  $values */
    private function markSend(Contact $contact, ?string $token, array $values): void
    {
        $query = CampaignSend::query()->where('contact_id', $contact->id);
        $send = $token ? (clone $query)->where('token', $token)->first() : null;
        // Without a token, attribute the event to the contact's most recent email.
        $send ??= $query->whereNotNull('sent_at')->latest('sent_at')->first();
        $send?->update($values);
    }
}
