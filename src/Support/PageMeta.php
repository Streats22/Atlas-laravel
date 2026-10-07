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
    private function __construct(private readonly array $data)
    {
    }

    public static function from(mixed $value): self
    {
        return new self(is_array($value) ? $value : []);
    }

    /** Keep only keys Atlas understands, with valid values; drop everything else. */
    public static function sanitize(array $input): self
    {
        $clean = [];
        foreach (self::validators() as $key => $validate) {
            $value = $validate($input[$key] ?? null);
            if ($value !== null && $value !== '') {
                $clean[$key] = $value;
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

    /** @return array<string, \Closure(mixed): mixed> One validator per scalar key; null = invalid. */
    private static function validators(): array
    {
        $text = static fn (int $max) => static fn ($v) => is_string($v) ? mb_substr(trim($v), 0, $max) : null;
        $hex = static fn ($v) => is_string($v) && preg_match(Theme::HEX_COLOR, $v) ? $v : null;

        return [
            'description' => $text(500),
            'og_image' => $text(500),
            'theme' => static fn ($v) => ThemeMode::tryFrom((string) $v)?->value,
            'spacing' => static fn ($v) => Spacing::tryFrom((string) $v)?->value,
            'theme_toggle' => static fn ($v) => $v === null || $v === '' ? null : (bool) $v,
            'accent' => $hex,
            'accent_dark' => $hex,
            'font' => static fn ($v) => is_string($v) && isset(Theme::FONTS[$v]) ? $v : null,
            'heading_font' => static fn ($v) => is_string($v) && ($v === 'same' || isset(Theme::FONTS[$v])) ? $v : null,
        ];
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
