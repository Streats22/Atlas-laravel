<?php

declare(strict_types=1);

namespace App\Enums;

enum StepType: string
{
    case Email = 'email';
    case Wait = 'wait';
    case Tag = 'tag';
    case Untag = 'untag';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Send email',
            self::Wait => 'Wait',
            self::Tag => 'Add tag',
            self::Untag => 'Remove tag',
        };
    }
}
