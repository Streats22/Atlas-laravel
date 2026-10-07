<?php

declare(strict_types=1);

namespace Atlas\Enums;

/** Vertical rhythm between sibling blocks (drives --atlas-space). */
enum Spacing: string
{
    case Compact = 'compact';
    case Comfortable = 'comfortable';
    case Spacious = 'spacious';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function cssValue(): string
    {
        return match ($this) {
            self::Compact => '.75rem',
            self::Comfortable => '1.5rem',
            self::Spacious => '2.5rem',
        };
    }

    public function sectionPadding(): string
    {
        return match ($this) {
            self::Compact => '40px',
            self::Comfortable => '72px',
            self::Spacious => '112px',
        };
    }
}
