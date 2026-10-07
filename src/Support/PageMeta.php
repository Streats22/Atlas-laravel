<?php

declare(strict_types=1);

namespace Atlas\Support;

use Atlas\Enums\Spacing;
use Atlas\Enums\ThemeMode;

/**
 * The whitelisted, typed view of a page's `meta` column
 * (SEO, theme settings and per-locale titles/descriptions).
 */
final class PageMeta
{
    /** Scalar keys that may be stored. */
    private const SCALARS = [
        'description', 'theme', 'theme_toggle', 'accent', 'accent_dark',
        'font', 'heading_font', 'spacing', 'og_image',
    ];

    private function __construct(private readonly array $data)
    {
    }

    public static function from(mixed $value): self
    {
        return new self(is_array($value) ? $value : []);
    }

    /** Keep only keys Atlas understands; drop empty values and unknown locales. */
    public static function sanitize(array $input): self
    {
        $clean = [];
        foreach (self::SCALARS as $key) {
            $value = $input[$key] ?? null;
            if ($value !== null && $value !== '') {
                $clean[$key] = $key === 'theme_toggle' ? (bool) $value : $value;
            }
        }

        foreach (['titles', 'descriptions'] as $key) {
            $perLocale = self::perLocale((array) ($input[$key] ?? []));
            if ($perLocale !== []) {
                $clean[$key] = $perLocale;
            }
        }

        return new self($clean);
    }

    /** @return array<string, string> */
    private static function perLocale(array $map): array
    {
        $known = array_intersect_key($map, Locales::available());

        return array_filter($known, static fn ($value) => is_string($value) && $value !== '');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function title(string $locale, string $fallback): string
    {
        return (string) ($this->data['titles'][$locale] ?? '') ?: $fallback;
    }

    public function description(string $locale): ?string
    {
        return ($this->data['descriptions'][$locale] ?? '') ?: ($this->data['description'] ?? null);
    }

    public function themeMode(?ThemeMode $default = null): ?ThemeMode
    {
        return ThemeMode::tryFrom((string) ($this->data['theme'] ?? '')) ?? $default;
    }

    public function spacing(?Spacing $default = null): ?Spacing
    {
        return Spacing::tryFrom((string) ($this->data['spacing'] ?? '')) ?? $default;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
