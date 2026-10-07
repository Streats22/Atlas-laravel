<?php

declare(strict_types=1);

namespace Atlas\Support;

/** Small helpers exposed to block views as $pick, $safe, $int. */
final class ViewHelpers
{
    /** Return $value if it is one of $allowed, otherwise $default (whitelists values used in classes/styles). */
    public static function pick(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    /** Clamp a numeric-ish value into [min, max]. */
    public static function int(mixed $value, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $number = is_numeric($value) ? (int) $value : $default;

        return max($min ?? PHP_INT_MIN, min($max ?? PHP_INT_MAX, $number));
    }

    /** A CSS aspect-ratio like "16/9", or $default. */
    public static function ratio(mixed $value, string $default = 'auto'): string
    {
        return is_string($value) && preg_match('/^\d+\/\d+$/', $value) ? $value : $default;
    }

    public static function safeUrl(mixed $url): string
    {
        return Url::safe(is_scalar($url) ? (string) $url : '');
    }
}
