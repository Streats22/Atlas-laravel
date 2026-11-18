<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteUser extends Notification
{
    public function __construct(private readonly string $token, private readonly string $invitedBy) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)
            ->subject('You are invited to '.config('app.name'))
            ->line("{$this->invitedBy} invited you to join ".config('app.name').' as '.$notifiable->role->value.'.')
            ->action('Choose your password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes. Ask for a new invite if it has expired.');
    }
}
