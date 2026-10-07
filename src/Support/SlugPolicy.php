<?php

declare(strict_types=1);

namespace Atlas\Support;

/** What counts as a valid, usable page slug (shared by the editor, imports and page creation). */
final class SlugPolicy
{
    /** The same rule as a route constraint (no delimiters/anchors). */
    public const ROUTE_PATTERN = '[a-z0-9]+(?:[\-\/][a-z0-9]+)*';

    public const PATTERN = '/^' . self::ROUTE_PATTERN . '$/';

    public static function isValid(string $slug): bool
    {
        return strlen($slug) <= 190 && preg_match(self::PATTERN, $slug) === 1;
    }

    /** True when the slug would collide with the editor's own URL space (e.g. "atlas" or "atlas/x"). */
    public static function isReserved(string $slug): bool
    {
        $reserved = trim((string) config('atlas.path'), '/');

        return $reserved !== '' && ($slug === $reserved || str_starts_with($slug, $reserved . '/'));
    }

    public static function usable(string $slug): bool
    {
        return self::isValid($slug) && ! self::isReserved($slug);
    }

    /** Turn a free-text base into a usable slug and make it unique with $exists. */
    public static function unique(string $base, \Closure $exists): string
    {
        $slug = $base !== '' ? $base : 'page';
        if (self::isReserved($slug)) {
            $slug .= '-page';
        }

        $candidate = $slug;
        for ($i = 2; $exists($candidate); $i++) {
            $candidate = $slug . '-' . $i;
        }

        return $candidate;
    }
}
