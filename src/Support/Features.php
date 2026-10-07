<?php

declare(strict_types=1);

namespace Atlas\Support;

/** The single source of truth for feature switches that gate dangerous capabilities. */
final class Features
{
    /** Raw HTML / CSS / JS (Custom Code block, page code, builder blocks). */
    public static function customCode(): bool
    {
        return (bool) config('atlas.custom_code');
    }

    /** Editor-written Blade/PHP — arbitrary code execution, off by default. */
    public static function bladeCode(): bool
    {
        return (bool) config('atlas.allow_blade_code');
    }
}
