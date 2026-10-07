<?php

namespace Atlas\Support;

/** Builds the CSS variables for light / dark mode and the page's theme settings. */
class Theme
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

    /** Page theme settings merged over config defaults. */
    public static function settings(array $meta = []): array
    {
        $d = (array) config('atlas.theme', []);
        $pick = fn ($key, $fallback) => ($meta[$key] ?? '') !== '' && $meta[$key] !== null ? $meta[$key] : ($d[$key] ?? $fallback);

        $mode = $pick('theme', 'auto');
        $color = fn ($v, $fb) => is_string($v) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : $fb;

        return [
            'mode' => in_array($mode, ['auto', 'light', 'dark'], true) ? $mode : 'auto',
            'toggle' => (bool) ($meta['theme_toggle'] ?? ($d['toggle'] ?? false)),
            'accent' => $color($pick('accent', '#4f46e5'), '#4f46e5'),
            'accent_dark' => $color($pick('accent_dark', '#818cf8'), '#818cf8'),
            'font' => isset(self::FONTS[$pick('font', 'system')]) ? $pick('font', 'system') : 'system',
            'heading_font' => isset(self::FONTS[$pick('heading_font', 'same')]) ? $pick('heading_font', 'same') : 'same',
        ];
    }

    /** Readable text colour (black/white) on a given hex background. */
    public static function contrast(string $hex): string
    {
        $h = ltrim($hex, '#');
        if (strlen($h) < 6) {
            $h = preg_replace('/(.)/', '$1$1', substr($h, 0, 3));
        }
        [$r, $g, $b] = [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#0f172a' : '#ffffff';
    }

    private static function block(array $vars, string $accent): string
    {
        $css = '';
        foreach ($vars as $k => $v) {
            $css .= "--atlas-{$k}:{$v};";
        }

        return $css."--atlas-accent:{$accent};--atlas-accent-contrast:".self::contrast($accent).';';
    }

    public static function css(array $t): string
    {
        $light = self::block(array_merge(self::LIGHT, (array) config('atlas.theme.light', [])), $t['accent']);
        $dark = self::block(array_merge(self::DARK, (array) config('atlas.theme.dark', [])), $t['accent_dark']);

        $font = self::FONTS[$t['font']];
        $heading = $t['heading_font'] === 'same' ? 'var(--atlas-font)' : self::FONTS[$t['heading_font']];

        $css = ":root{{$light}--atlas-font:{$font};--atlas-heading-font:{$heading};color-scheme:light;}";
        if ($t['mode'] === 'dark') {
            $css .= ":root{{$dark}color-scheme:dark;}";
        } elseif ($t['mode'] === 'auto') {
            $css .= "@media (prefers-color-scheme: dark){:root:not([data-atlas-theme=light]){{$dark}color-scheme:dark;}}";
        }
        $css .= ":root[data-atlas-theme=dark]{{$dark}color-scheme:dark;}:root[data-atlas-theme=light]{{$light}color-scheme:light;}";

        return $css;
    }
}
