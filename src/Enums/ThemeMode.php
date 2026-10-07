<?php

declare(strict_types=1);

namespace Atlas\Enums;

enum ThemeMode: string
{
    case Auto = 'auto';
    case Light = 'light';
    case Dark = 'dark';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
