<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Mail;

/**
 * Applies the sending provider configured in the UI on top of config/mail.php.
 * Every provider is reached over SMTP so no extra SDKs are needed.
 */
final class MailConfig
{
    /** Provider presets: host, port, encryption, username hint, DNS setup docs. */
    public const PROVIDERS = [
        'smtp' => ['label' => 'Custom SMTP server', 'host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => ''],
        'mailgun' => ['label' => 'Mailgun', 'host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls', 'username' => 'postmaster@your-domain'],
        'postmark' => ['label' => 'Postmark', 'host' => 'smtp.postmarkapp.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'Server API token'],
        'ses' => ['label' => 'Amazon SES', 'host' => 'email-smtp.eu-west-1.amazonaws.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'SMTP username (not your AWS key)'],
        'sendgrid' => ['label' => 'SendGrid', 'host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls', 'username' => 'apikey'],
        'brevo' => ['label' => 'Brevo', 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'your Brevo login'],
        'resend' => ['label' => 'Resend', 'host' => 'smtp.resend.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'resend'],
        'log' => ['label' => 'Log only (development, nothing is sent)', 'host' => '', 'port' => 0, 'encryption' => '', 'username' => ''],
    ];

    public function __construct(private readonly Settings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->get('mail_provider') !== null;
    }

    public function apply(): void
    {
        $provider = $this->settings->get('mail_provider');
        if ($provider === null) {
            return;
        }

        if ($provider === 'log') {
            config(['mail.default' => 'log']);
        } else {
            $encryption = $this->settings->get('mail_encryption', 'tls');
            config([
                'mail.default' => 'mailroom',
                'mail.mailers.mailroom' => [
                    'transport' => 'smtp',
                    'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                    'host' => $this->settings->get('mail_host'),
                    'port' => (int) $this->settings->get('mail_port', '587'),
                    'username' => $this->settings->get('mail_username'),
                    'password' => $this->settings->get('mail_password'),
                    'timeout' => 20,
                    'require_tls' => $encryption === 'tls',
                ],
            ]);
        }
        if ($from = $this->settings->get('mail_from_address')) {
            config(['mail.from' => ['address' => $from, 'name' => $this->settings->get('mail_from_name', config('app.name'))]]);
        }

        // Long-running queue workers keep resolved mailers; drop them so new settings take effect.
        Mail::purge('mailroom');
        Mail::purge('log');
    }

    public function perMinute(): int
    {
        return max(0, (int) $this->settings->get('send_rate_per_minute', '0'));
    }
}
