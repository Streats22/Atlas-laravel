<?php

declare(strict_types=1);

namespace Atlas\Support;

use Atlas\Enums\Spacing;
use Atlas\Enums\ThemeMode;

/** Resolved theme settings for one page, and the CSS they produce. */
final class Theme
{
    public const FONTS = [
        'system' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        'serif' => 'Georgia, "Times New Roman", Times, serif',
        'mono' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
        'rounded' => 'ui-rounded, "Nunito", "Segoe UI", system-ui, sans-serif',
    ];

    public const LIGHT = [
        'bg' => '#ffffff', 'surface' => '#f8fafc', 'surface-2' => '#eef2f7', 'text' => '#0f172a',
        'muted' => '#586174', 'border' => '#e2e8f0', 'shadow' => '0 10px 30px rgba(15,23,42,.10)',
    ];

    public const DARK = [
        'bg' => '#0b1020', 'surface' => '#131a2e', 'surface-2' => '#1b2440', 'text' => '#e6e9f2',
        'muted' => '#a0aac0', 'border' => '#273252', 'shadow' => '0 10px 30px rgba(0,0,0,.45)',
    ];

    private const DEFAULT_ACCENT = '#4f46e5';

    private const DEFAULT_ACCENT_DARK = '#818cf8';

    public function __construct(
        public readonly ThemeMode $mode,
        public readonly bool $toggle,
        public readonly string $accent,
        public readonly string $accentDark,
        public readonly string $font,
        public readonly string $headingFont,
        public readonly Spacing $spacing,
    ) {
    }

    /** Page settings merged over the config defaults. */
    public static function fromMeta(PageMeta $meta): self
    {
        $defaults = (array) config('atlas.theme', []);
        $pick = static fn (string $key, mixed $fallback) => $meta->get($key, $defaults[$key] ?? $fallback);

        return new self(
            mode: $meta->themeMode() ?? ThemeMode::tryFrom((string) ($defaults['mode'] ?? '')) ?? ThemeMode::Auto,
            toggle: (bool) $meta->get('theme_toggle', $defaults['toggle'] ?? false),
            accent: self::color($pick('accent', self::DEFAULT_ACCENT), self::DEFAULT_ACCENT),
            accentDark: self::color($pick('accent_dark', self::DEFAULT_ACCENT_DARK), self::DEFAULT_ACCENT_DARK),
            font: self::fontKey($pick('font', 'system'), 'system'),
            headingFont: self::fontKey($pick('heading_font', 'same'), 'same'),
            spacing: $meta->spacing() ?? Spacing::tryFrom((string) ($defaults['spacing'] ?? '')) ?? Spacing::Comfortable,
        );
    }

    public static function default(): self
    {
        return self::fromMeta(PageMeta::from([]));
    }

    private static function color(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : $fallback;
    }

    private static function fontKey(mixed $value, string $fallback): string
    {
        return is_string($value) && (isset(self::FONTS[$value]) || $value === 'same') ? $value : $fallback;
    }

    /** Readable text colour (dark/white) on a given hex background. */
    public static function contrast(string $hex): string
    {
        $h = ltrim($hex, '#');
        if (strlen($h) < 6) {
            $h = (string) preg_replace('/(.)/', '$1$1', substr($h, 0, 3));
        }
        [$r, $g, $b] = [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#0f172a' : '#ffffff';
    }

    /** @return array<string, mixed> Shape consumed by the editor. */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'toggle' => $this->toggle,
            'accent' => $this->accent,
            'accent_dark' => $this->accentDark,
            'font' => $this->font,
            'heading_font' => $this->headingFont,
            'spacing' => $this->spacing->value,
        ];
    }

    private function declarations(array $tokens, string $accent): string
    {
        $css = '';
        foreach ($tokens as $name => $value) {
            $css .= "--atlas-{$name}:{$value};";
        }

        return $css . "--atlas-accent:{$accent};--atlas-accent-contrast:" . self::contrast($accent) . ';';
    }

    public function css(): string
    {
        $light = $this->declarations(array_merge(self::LIGHT, (array) config('atlas.theme.light', [])), $this->accent);
        $dark = $this->declarations(array_merge(self::DARK, (array) config('atlas.theme.dark', [])), $this->accentDark);
        $heading = $this->headingFont === 'same' ? 'var(--atlas-font)' : self::FONTS[$this->headingFont];
        $rhythm = '--atlas-space:' . $this->spacing->cssValue() . ';--atlas-section-y:' . $this->spacing->sectionPadding() . ';';

        $css = ":root{{$light}--atlas-font:" . self::FONTS[$this->font] . ";--atlas-heading-font:{$heading};{$rhythm}color-scheme:light;}";

        if ($this->mode === ThemeMode::Dark) {
            $css .= ":root{{$dark}color-scheme:dark;}";
        } elseif ($this->mode === ThemeMode::Auto) {
            $css .= "@media (prefers-color-scheme: dark){:root:not([data-atlas-theme=light]){{$dark}color-scheme:dark;}}";
        }

        return $css . ":root[data-atlas-theme=dark]{{$dark}color-scheme:dark;}:root[data-atlas-theme=light]{{$light}color-scheme:light;}";
    }

    /** Runs before first paint so the right theme is set without a flash. */
    public function earlyScript(): string
    {
        $cfg = json_encode(['mode' => $this->mode->value, 'toggle' => $this->toggle], JSON_THROW_ON_ERROR);

        return "(function(d,c){d.classList.add('atlas-js');var m=c.mode;try{if(c.toggle){var s=localStorage.getItem('atlas-theme');"
            . "if(s==='light'||s==='dark')m=s}}catch(e){}d.setAttribute('data-atlas-theme',m)})(document.documentElement,{$cfg});";
    }
}
