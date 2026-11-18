<?php

declare(strict_types=1);

namespace App\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Birthday = 'birthday';
    case Dropdown = 'dropdown';
    case Phone = 'phone';
    case Url = 'url';

    public function label(): string
    {
        return match ($this) {
            self::Birthday => 'Birthday (month/day)',
            self::Url => 'Website',
            default => ucfirst($this->value),
        };
    }

    /** @return list<string|object> */
    public function rules(): array
    {
        return match ($this) {
            self::Text => ['string', 'max:255'],
            self::Number => ['numeric', 'between:-1000000000,1000000000'],
            self::Date => ['date_format:Y-m-d'],
            self::Birthday => ['regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            self::Dropdown => ['string', 'max:100'],
            self::Phone => ['string', 'max:40', 'regex:/^[0-9 +().-]+$/'],
            self::Url => ['url:http,https', 'max:255'],
        };
    }

    public function isDate(): bool
    {
        return $this === self::Date || $this === self::Birthday;
    }
}
