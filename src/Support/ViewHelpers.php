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

    /**
     * A URL that is safe inside CSS url('…'): scheme allow-listed, and every character that
     * could close the string or the declaration is percent-encoded.
     */
    public static function cssUrl(mixed $url): string
    {
        $safe = self::safeUrl($url);
        $safe = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $safe);

        return strtr($safe, ["'" => '%27', '"' => '%22', '(' => '%28', ')' => '%29', '\\' => '%5C', ';' => '%3B', '<' => '%3C', '>' => '%3E', ' ' => '%20']);
    }

    /** A CSS colour (hex, rgb/hsl, a keyword or an --atlas-* variable), or '' when it is not one. */
    public static function cssColor(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match(Theme::HEX_COLOR, $value)
            || preg_match('/^(rgb|hsl)a?\(\s*[\d.]+%?\s*[, ]\s*[\d.]+%?\s*[, ]\s*[\d.]+%?\s*([,\/]\s*[\d.]+%?\s*)?\)$/i', $value)
            || preg_match('/^var\(--atlas-[a-z0-9-]+\)$/', $value)
            || preg_match('/^[a-zA-Z]{3,24}$/', $value)
                ? $value
                : '';
    }

    /** A CSS length such as 100%, 320px or 2.5rem (or "auto"), otherwise $default. */
    public static function cssLength(mixed $value, string $default = 'auto'): string
    {
        $value = is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';

        return preg_match('/^(auto|\d+(\.\d+)?(px|%|rem|em|vw|vh|ch)?)$/', $value) ? $value : $default;
    }
}
