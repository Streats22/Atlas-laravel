<?php

declare(strict_types=1);

namespace App\Enums;

enum AutomationTrigger: string
{
    case Subscribed = 'subscribed';
    case TagAdded = 'tag_added';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Subscribed => 'Contact subscribes to the audience',
            self::TagAdded => 'Tag is added to a contact',
            self::Manual => 'Added manually (from a segment)',
        };
    }
}
