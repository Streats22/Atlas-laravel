<?php

declare(strict_types=1);

namespace Atlas\Rendering;

use Atlas\Atlas;
use Atlas\Support\Locales;
use Atlas\Support\Theme;
use Atlas\Support\Url;
use Illuminate\Support\HtmlString;

/** Builds the variables handed to a layout view: <head>, scripts, body and <html> attributes. */
final class DocumentBuilder
{
    private static array $files = [];

    public function __construct(private readonly Atlas $atlas)
    {
    }

    private static function file(string $name): string
    {
        return self::$files[$name] ??= (string) file_get_contents(__DIR__ . '/../../resources/dist/' . $name);
    }

    public function build(PageData $page, HtmlString $body, AssetBag $assets, bool $editing): array
    {
        $locale = Locales::current();
        $theme = Theme::fromMeta($page->meta);
        $custom = (bool) config('atlas.custom_code');
        $title = $page->meta->title($locale, $page->title);
        $description = $page->meta->description($locale);
        $html = (string) $body;
        $dir = Locales::isRtl($locale) ? 'rtl' : 'ltr';

        return [
            'page' => $page->model ?? (object) ['title' => $page->title],
            'title' => $title,
            'description' => $description,
            'content' => new HtmlString($this->main($html, $theme, $editing)),
            'head' => new HtmlString($this->head($page, $theme, $assets, $title, $description, $editing, $custom)),
            'scripts' => new HtmlString($this->scripts($page, $theme, $assets, $html, $editing, $custom)),
            'editing' => $editing,
            'locale' => $locale,
            'dir' => $dir,
            'htmlAttributes' => new HtmlString(
                'lang="' . e(str_replace('_', '-', $locale)) . '" dir="' . $dir . '" data-atlas-theme="' . e($theme->mode->value) . '"',
            ),
        ];
    }

    /** Root blocks sit inside <main> so loose blocks get the same gutters and rhythm as sections. */
    private function main(string $html, Theme $theme, bool $editing): string
    {
        $main = '<main class="atlas-main">' . $html . '</main>';

        if ($theme->toggle && ! $editing) {
            $main .= '<button type="button" class="atlas-theme-fab" data-atlas-theme-toggle data-atlas-rt aria-label="'
                . e(__('atlas::ui.toggle_theme')) . '"><span class="atlas-ico-sun">☀</span><span class="atlas-ico-moon">☾</span></button>';
        }

        return $main;
    }

    private function head(PageData $page, Theme $theme, AssetBag $assets, string $title, ?string $description, bool $editing, bool $custom): string
    {
        $head = '<style id="atlas-base">' . self::file('base.css') . "</style>\n"
            . '<style id="atlas-theme">' . $theme->css() . "</style>\n";

        if (! $editing) {
            $head .= '<script>' . $theme->earlyScript() . "</script>\n";
        }

        foreach (array_merge($this->atlas->styles(), $assets->styles()) as $url) {
            $head .= '<link rel="stylesheet" href="' . e($url) . "\">\n";
        }

        if ($editing) {
            $head .= '<style>' . self::file('canvas.css') . "</style>\n";
        } elseif ($page->model !== null) {
            $head .= $this->socialTags($page, $title, $description);
        }

        if ($custom && filled($page->css)) {
            $head .= '<style id="atlas-page-css">' . $page->css . "</style>\n";
        }
        if ($custom && filled($page->head)) {
            $head .= $page->head . "\n";
        }

        return $head;
    }

    private function socialTags(PageData $page, string $title, ?string $description): string
    {
        $tags = '<link rel="canonical" href="' . e(Locales::url($page->model, Locales::current())) . "\">\n";
        $tags .= '<meta property="og:title" content="' . e($title) . "\">\n";

        if ($description) {
            $tags .= '<meta property="og:description" content="' . e($description) . "\">\n";
        }
        if ($image = $page->meta->get('og_image')) {
            $tags .= '<meta property="og:image" content="' . e(Url::safe((string) $image)) . "\">\n";
        }
        if (Locales::multiple()) {
            foreach (array_keys(Locales::available()) as $code) {
                $tags .= '<link rel="alternate" hreflang="' . e($code) . '" href="' . e(Locales::url($page->model, $code)) . "\">\n";
            }
        }

        return $tags;
    }

    private function scripts(PageData $page, Theme $theme, AssetBag $assets, string $html, bool $editing, bool $custom): string
    {
        if ($editing) {
            return '';
        }

        $scripts = '';
        $external = array_merge(
            $this->atlas->scripts(),
            $assets->scripts(),
        );
        foreach ($external as $script) {
            $scripts .= '<script src="' . e($script['src']) . '"' . ($script['defer'] ? ' defer' : '') . "></script>\n";
        }

        if ($this->needsRuntime($theme, $html)) {
            $scripts .= '<script>window.AtlasI18n=' . json_encode($this->runtimeLabels(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR) . ";</script>\n"
                . '<script id="atlas-runtime">' . self::file('runtime.js') . "</script>\n";
        }
        if ($custom && filled($page->js)) {
            $scripts .= '<script id="atlas-page-js">' . $page->js . "</script>\n";
        }

        return $scripts;
    }

    /** Translated strings the runtime needs for dynamically created controls. */
    private function runtimeLabels(): array
    {
        return [
            'close' => __('atlas::ui.a11y_close'),
            'prev' => __('atlas::ui.a11y_prev'),
            'next' => __('atlas::ui.a11y_next'),
            'slide' => __('atlas::ui.a11y_slide', ['n' => ':n']),
        ];
    }

    /** The runtime is only inlined on pages whose markup asks for it. */
    private function needsRuntime(Theme $theme, string $html): bool
    {
        return $theme->toggle || str_contains($html, 'data-atlas-rt') || str_contains($html, 'data-atlas-anim');
    }
}
