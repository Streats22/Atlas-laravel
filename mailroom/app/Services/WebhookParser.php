<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Normalises bounce/complaint webhooks from common providers into
 * list<array{type:'hard'|'soft'|'complaint', email:string}>.
 */
final class WebhookParser
{
    /** @return list<array{type:string, email:string}> */
    public function parse(string $provider, array $payload): array
    {
        $events = match ($provider) {
            'postmark' => [$this->postmark($payload)],
            'mailgun' => [$this->mailgun($payload['event-data'] ?? $payload)],
            'ses' => $this->ses($payload),
            'sendgrid' => array_map(fn ($e) => is_array($e) ? $this->sendgrid($e) : null, array_is_list($payload) ? $payload : [$payload]),
            'resend' => $this->resend($payload),
            'brevo' => [$this->brevo($payload)],
            default => [$this->generic($payload)],
        };

        return array_values(array_filter($events, fn ($e) => is_array($e) && filter_var($e['email'] ?? '', FILTER_VALIDATE_EMAIL)));
    }

    private function postmark(array $p): ?array
    {
        return match ($p['RecordType'] ?? '') {
            'Bounce' => ['type' => in_array($p['Type'] ?? '', ['HardBounce', 'BadEmailAddress', 'ManuallyDeactivated'], true) ? 'hard' : 'soft', 'email' => (string) ($p['Email'] ?? '')],
            'SpamComplaint' => ['type' => 'complaint', 'email' => (string) ($p['Email'] ?? '')],
            default => null,
        };
    }

    private function mailgun(array $e): ?array
    {
        return match ($e['event'] ?? '') {
            'failed' => ['type' => ($e['severity'] ?? '') === 'permanent' ? 'hard' : 'soft', 'email' => (string) ($e['recipient'] ?? '')],
            'complained' => ['type' => 'complaint', 'email' => (string) ($e['recipient'] ?? '')],
            default => null,
        };
    }

    /** @return list<array{type:string, email:string}> */
    private function ses(array $p): array
    {
        $message = isset($p['Message']) && is_string($p['Message']) ? (json_decode($p['Message'], true) ?: []) : $p;
        $type = $message['notificationType'] ?? $message['eventType'] ?? '';
        if ($type === 'Bounce') {
            $hard = ($message['bounce']['bounceType'] ?? '') === 'Permanent';

            return array_map(fn ($r) => ['type' => $hard ? 'hard' : 'soft', 'email' => (string) ($r['emailAddress'] ?? '')], $message['bounce']['bouncedRecipients'] ?? []);
        }
        if ($type === 'Complaint') {
            return array_map(fn ($r) => ['type' => 'complaint', 'email' => (string) ($r['emailAddress'] ?? '')], $message['complaint']['complainedRecipients'] ?? []);
        }

        return [];
    }

    private function sendgrid(array $e): ?array
    {
        return match ($e['event'] ?? '') {
            'bounce' => ['type' => ($e['type'] ?? 'bounce') === 'blocked' ? 'soft' : 'hard', 'email' => (string) ($e['email'] ?? '')],
            'dropped' => ['type' => 'hard', 'email' => (string) ($e['email'] ?? '')],
            'deferred' => ['type' => 'soft', 'email' => (string) ($e['email'] ?? '')],
            'spamreport' => ['type' => 'complaint', 'email' => (string) ($e['email'] ?? '')],
            default => null,
        };
    }

    /** @return list<array{type:string, email:string}> */
    private function resend(array $p): array
    {
        $type = match ($p['type'] ?? '') {
            'email.bounced' => 'hard',
            'email.complained' => 'complaint',
            default => null,
        };

        return $type ? array_map(fn ($to) => ['type' => $type, 'email' => (string) $to], (array) ($p['data']['to'] ?? [])) : [];
    }

    private function brevo(array $p): ?array
    {
        return match ($p['event'] ?? '') {
            'hard_bounce', 'invalid_email', 'blocked' => ['type' => 'hard', 'email' => (string) ($p['email'] ?? '')],
            'soft_bounce' => ['type' => 'soft', 'email' => (string) ($p['email'] ?? '')],
            'spam', 'complaint' => ['type' => 'complaint', 'email' => (string) ($p['email'] ?? '')],
            default => null,
        };
    }

    private function generic(array $p): ?array
    {
        $type = match ($p['type'] ?? '') {
            'bounce', 'hard_bounce', 'hard' => 'hard',
            'soft_bounce', 'soft' => 'soft',
            'complaint', 'spam' => 'complaint',
            default => null,
        };

        return $type ? ['type' => $type, 'email' => (string) ($p['email'] ?? '')] : null;
    }
}
