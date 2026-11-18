<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ConfirmSubscriptionMail extends Mailable
{
    public function __construct(public readonly Contact $contact) {}

    public function envelope(): Envelope
    {
        $audience = $this->contact->audience;

        return new Envelope(
            from: new Address($audience->from_email, $audience->from_name),
            subject: 'Please confirm your subscription to '.$audience->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirm', with: [
            'audience' => $this->contact->audience,
            'url' => route('public.confirm', $this->contact->token),
        ]);
    }
}
