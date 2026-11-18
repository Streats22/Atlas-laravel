<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use App\Services\EmailRenderer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class CampaignMail extends Mailable
{
    public function __construct(
        public readonly Campaign $campaign,
        public readonly Contact $contact,
        public readonly ?CampaignSend $send = null,
    ) {}

    public function envelope(): Envelope
    {
        $renderer = app(EmailRenderer::class);
        $subject = $renderer->subject($this->campaign, $this->contact);

        return new Envelope(
            from: new Address($this->campaign->from_email, $this->campaign->from_name),
            subject: $this->send === null ? '[Test] '.$subject : $subject,
        );
    }

    public function headers(): Headers
    {
        $url = route('public.unsubscribe', ['token' => $this->contact->token, 'send' => $this->send?->token]);

        // RFC 8058 one-click unsubscribe, required by Gmail/Yahoo for bulk senders.
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$url.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(htmlString: app(EmailRenderer::class)->render($this->campaign, $this->contact, $this->send));
    }
}
