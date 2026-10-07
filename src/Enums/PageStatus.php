<?php

declare(strict_types=1);

namespace Atlas\Enums;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('atlas::ui.' . $this->value);
    }
}
