<?php

declare(strict_types=1);

namespace App\Builder;

/** Whitelists for values that end up in attributes or inline styles. */
final class Values
{
    public static function color(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? strtolower($value) : $default;
    }

    /** @param  list<string>  $allowed */
    public static function pick(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    public static function int(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    public static function text(mixed $value, int $max = 500): string
    {
        return is_scalar($value) ? mb_substr(trim((string) $value), 0, $max) : '';
    }

    /** http(s)/mailto/tel URLs and the unsubscribe merge tag; everything else becomes ''. */
    public static function url(mixed $value): string
    {
        $url = trim(is_string($value) ? $value : '');
        if (preg_match('/^\{\{\s*unsubscribe_url\s*\}\}$/', $url)) {
            return '{{ unsubscribe_url }}';
        }
        if (preg_match('#^(https?://|mailto:|tel:)#i', $url) && ! preg_match('/[\s<>"\'`]/', $url)) {
            return mb_substr($url, 0, 2000);
        }

        return '';
    }
}
