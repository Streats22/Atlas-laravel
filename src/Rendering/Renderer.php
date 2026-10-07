<?php

namespace Atlas\Rendering;

use Atlas\Atlas;
use Atlas\Blocks\CommonFields;
use Atlas\Models\Page;
use Atlas\Support\Locales;
use Atlas\Support\Theme;
use Illuminate\Support\HtmlString;
use Throwable;

class Renderer
{
    /** Styles / scripts requested by the blocks rendered so far. */
    protected array $assets = ['styles' => [], 'scripts' => []];

    protected static array $fileCache = [];

    public function __construct(protected Atlas $atlas) {}

    /** Replace the {{selector}} placeholder with the block's DOM id selector. */
    public static function scopeCss(string $css, string $domId): string
    {
        return str_replace('{{selector}}', '#'.$domId, $css);
    }

    protected static function file(string $name): string
    {
        return self::$fileCache[$name] ??= (string) file_get_contents(__DIR__.'/../../resources/dist/'.$name);
    }

    /** Render a block tree to HTML. */
    public function render(array $nodes, bool $editing = false): HtmlString
    {
        $html = '';
        foreach ($nodes as $node) {
            if (is_array($node)) {
                $html .= $this->renderNode($node, $editing);
            }
        }

        return new HtmlString($html);
    }

    /**
     * Pick the current locale's value for every translatable field.
     * Translations live next to the default value as "name@locale".
     */
    public function localize(array $fields, array $props, string $locale, string $default): array
    {
        foreach ($fields as $field) {
            $name = $field['name'];

            if (($field['type'] ?? '') === 'repeater' && is_array($props[$name] ?? null)) {
                $props[$name] = array_map(
                    fn ($item) => is_array($item) ? $this->localize($field['fields'] ?? [], $item, $locale, $default) : $item,
                    array_values($props[$name])
                );
            } elseif (! empty($field['translatable']) && $locale !== $default) {
                $translated = $props[$name.'@'.$locale] ?? null;
                if (is_string($translated) && $translated !== '') {
                    $props[$name] = $translated;
                }
            }
        }

        return $props;
    }

    protected function renderNode(array $node, bool $editing): string
    {
        $type = (string) ($node['type'] ?? '');
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($node['id'] ?? '')) ?: bin2hex(random_bytes(4));
        $block = $this->atlas->blocks()->get($type);

        if (! $block) {
            return $editing
                ? $this->wrap($id, 'atlas-unknown', false, '<div class="atlas-placeholder">Unknown block “'.e($type).'”</div>', $node, [])
                : '';
        }

        $fields = $block->fields();
        $props = array_merge($block->defaults(), CommonFields::defaults(), (array) ($node['props'] ?? []));
        $props = $this->localize($fields, $props, Locales::current(), Locales::default());

        $children = $block->container() ? $this->render($node['children'] ?? [], $editing) : new HtmlString('');

        if ($editing && $block->container() && ($node['children'] ?? []) === []) {
            $children = new HtmlString('<div class="atlas-placeholder" data-atlas-placeholder>'.e(__('atlas::ui.drop_here')).'</div>');
        }

        $customCode = (bool) config('atlas.custom_code');
        $domId = $customCode && ! empty($props['html_id'])
            ? (preg_replace('/[^A-Za-z0-9_-]/', '', (string) $props['html_id']) ?: 'atlas-'.$id)
            : 'atlas-'.$id;

        $node['id'] = $id;
        $node['dom_id'] = $domId;

        try {
            $inner = $block->render($props, $children, $node, $editing);
            $this->collectAssets($block->assets(), $editing);
        } catch (Throwable $e) {
            report($e);
            $inner = $editing
                ? '<div class="atlas-error"><strong>'.e($block->label()).' failed:</strong> '.e($e->getMessage()).'</div>'
                : '';
        }

        if ($customCode && filled($props['custom_css'] ?? null)) {
            $inner .= '<style>'.self::scopeCss((string) $props['custom_css'], $domId).'</style>';
        }

        return $this->wrap($id, 'atlas-b-'.$type, $block->container(), $inner, $node, $props, $editing, $domId, $type);
    }

    protected function collectAssets(array $assets, bool $editing): void
    {
        foreach ((array) ($assets['styles'] ?? []) as $url) {
            $this->assets['styles'][$url] = $url;
        }
        if (! $editing) {
            foreach ((array) ($assets['scripts'] ?? []) as $script) {
                $script = is_array($script) ? $script : ['src' => $script, 'defer' => false];
                if (filled($script['src'] ?? null)) {
                    $this->assets['scripts'][$script['src']] = $script;
                }
            }
        }
    }

    protected function wrap(string $id, string $class, bool $container, string $inner, array $node, array $props, bool $editing = true, ?string $domId = null, string $type = ''): string
    {
        $classes = ['atlas-block', $class];
        $attrs = 'id="'.e($domId ?? 'atlas-'.$id).'"';
        $style = '';

        if (! empty($props['css_class'])) {
            $classes[] = $props['css_class'];
        }
        if (in_array($props['visibility'] ?? 'all', ['hide-mobile', 'hide-desktop'], true)) {
            $classes[] = 'atlas-'.$props['visibility'];
        }
        if (in_array($props['anim_hover'] ?? 'none', ['lift', 'zoom', 'glow'], true)) {
            $classes[] = 'atlas-hover-'.$props['anim_hover'];
        }

        $anim = $props['anim'] ?? 'none';
        if ($anim !== 'none' && preg_match('/^[a-z-]+$/', (string) $anim)) {
            $attrs .= ' data-atlas-anim="'.e($anim).'"';
            $style .= '--atlas-dur:'.(int) ($props['anim_duration'] ?? 700).'ms;--atlas-delay:'.(int) ($props['anim_delay'] ?? 0).'ms;';
        }
        if ((int) ($props['margin_top'] ?? 0) !== 0) {
            $style .= 'margin-top:'.(int) $props['margin_top'].'px;';
        }
        if ((int) ($props['margin_bottom'] ?? 0) !== 0) {
            $style .= 'margin-bottom:'.(int) $props['margin_bottom'].'px;';
        }

        if ($editing) {
            $attrs .= ' data-atlas-id="'.e($id).'" data-atlas-type="'.e($type).'"'.($container ? ' data-atlas-container="1"' : '');
        }

        return '<div '.$attrs.' class="'.e(implode(' ', $classes)).'"'.($style ? ' style="'.e($style).'"' : '').'>'.$inner.'</div>';
    }

    /** Assemble the document (via the configured layout) for a page. */
    public function document(Page|array $page, bool $editing = false): string
    {
        return view(config('atlas.layout', 'atlas::layouts.document'), $this->layoutData($page, $editing))->render();
    }

    /** Variables handed to the layout view. */
    public function layoutData(Page|array $page, bool $editing = false): array
    {
        $get = fn (string $key, mixed $default = null) => $page instanceof Page ? ($page->{$key} ?? $default) : ($page[$key] ?? $default);
        $custom = (bool) config('atlas.custom_code');
        $meta = (array) ($get('meta') ?? []);
        $locale = Locales::current();
        $theme = Theme::settings($meta);

        $this->assets = ['styles' => [], 'scripts' => []];
        $content = $this->render($get('content', []) ?? [], $editing);
        $html = (string) $content;

        $title = ($meta['titles'][$locale] ?? '') ?: $get('title', '');
        $description = ($meta['descriptions'][$locale] ?? '') ?: ($meta['description'] ?? null);

        // ---- <head>
        $head = '<style id="atlas-base">'.self::file('base.css').'</style>'."\n";
        $head .= '<style id="atlas-theme">'.Theme::css($theme).'</style>'."\n";
        if (! $editing) {
            $head .= '<script>'.$this->earlyScript($theme).'</script>'."\n";
        }
        foreach ($this->atlas->styles() as $url) {
            $head .= '<link rel="stylesheet" href="'.e($url).'">'."\n";
        }
        foreach ($this->assets['styles'] as $url) {
            $head .= '<link rel="stylesheet" href="'.e($url).'">'."\n";
        }
        if ($editing) {
            $head .= '<style>'.self::file('canvas.css').'</style>'."\n";
        }
        if ($page instanceof Page && ! $editing) {
            $head .= '<meta property="og:title" content="'.e($title).'">'."\n";
            if ($description) {
                $head .= '<meta property="og:description" content="'.e($description).'">'."\n";
            }
            if (! empty($meta['og_image'])) {
                $head .= '<meta property="og:image" content="'.e(\Atlas\Support\Url::safe($meta['og_image'])).'">'."\n";
            }
            if (Locales::multiple()) {
                foreach (Locales::available() as $code => $name) {
                    $head .= '<link rel="alternate" hreflang="'.e($code).'" href="'.e(Locales::url($page, $code)).'">'."\n";
                }
            }
        }
        if ($custom && filled($get('css'))) {
            $head .= '<style id="atlas-page-css">'.$get('css').'</style>'."\n";
        }
        if ($custom && filled($get('head'))) {
            $head .= $get('head')."\n";
        }

        // ---- scripts
        $scripts = '';
        if (! $editing) {
            foreach ($this->atlas->scripts() as $script) {
                $scripts .= '<script src="'.e($script['src']).'"'.($script['defer'] ? ' defer' : '').'></script>'."\n";
            }
            foreach ($this->assets['scripts'] as $script) {
                $scripts .= '<script src="'.e($script['src']).'"'.(($script['defer'] ?? false) ? ' defer' : '').'></script>'."\n";
            }
            $needsRuntime = $theme['toggle'] || str_contains($html, 'data-atlas-rt') || str_contains($html, 'data-atlas-anim');
            if ($needsRuntime) {
                $scripts .= '<script id="atlas-runtime">'.self::file('runtime.js').'</script>'."\n";
            }
            if ($custom && filled($get('js'))) {
                $scripts .= '<script id="atlas-page-js">'.$get('js').'</script>'."\n";
            }
        }

        if ($theme['toggle'] && ! $editing) {
            $content = new HtmlString($html.'<button type="button" class="atlas-theme-fab" data-atlas-theme-toggle data-atlas-rt aria-label="'.e(__('atlas::ui.toggle_theme')).'"><span class="atlas-ico-sun">☀</span><span class="atlas-ico-moon">☾</span></button>');
        }

        return [
            'page' => $page instanceof Page ? $page : (object) $page,
            'title' => $title,
            'description' => $description,
            'content' => $content,
            'head' => new HtmlString($head),
            'scripts' => new HtmlString($scripts),
            'editing' => $editing,
            'locale' => $locale,
            'dir' => Locales::isRtl($locale) ? 'rtl' : 'ltr',
            'htmlAttributes' => new HtmlString('lang="'.e(str_replace('_', '-', $locale)).'" dir="'.(Locales::isRtl($locale) ? 'rtl' : 'ltr').'" data-atlas-theme="'.e($theme['mode']).'"'),
        ];
    }

    /** Runs before first paint: sets the theme without a flash. */
    protected function earlyScript(array $theme): string
    {
        $cfg = json_encode(['mode' => $theme['mode'], 'toggle' => $theme['toggle']]);

        return "(function(d,c){d.classList.add('atlas-js');var m=c.mode;try{if(c.toggle){var s=localStorage.getItem('atlas-theme');if(s==='light'||s==='dark')m=s}}catch(e){}d.setAttribute('data-atlas-theme',m)})(document.documentElement,{$cfg});";
    }
}
