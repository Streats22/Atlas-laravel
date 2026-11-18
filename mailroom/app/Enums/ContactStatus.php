<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactStatus: string
{
    case Subscribed = 'subscribed';
    case Pending = 'pending';
    case Unsubscribed = 'unsubscribed';
    case Cleaned = 'cleaned';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
