<?php

namespace Atlas\Rendering;

use Atlas\Atlas;
use Atlas\Blocks\Block;
use Atlas\Models\Page;
use Illuminate\Support\HtmlString;
use Throwable;

class Renderer
{
    public function __construct(protected Atlas $atlas) {}

    /** Replace the {{selector}} placeholder with the block's DOM id selector. */
    public static function scopeCss(string $css, string $domId): string
    {
        return str_replace('{{selector}}', '#'.$domId, $css);
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

    protected function renderNode(array $node, bool $editing): string
    {
        $type = (string) ($node['type'] ?? '');
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($node['id'] ?? '')) ?: bin2hex(random_bytes(4));
        $block = $this->atlas->blocks()->get($type);

        if (! $block) {
            return $editing
                ? $this->wrap($id, $type, 'atlas-unknown', false, '<div class="atlas-placeholder">Unknown block “'.e($type).'”</div>', $node)
                : '';
        }

        $props = array_merge($block->defaults(), (array) ($node['props'] ?? []));
        $children = $block->container() ? $this->render($node['children'] ?? [], $editing) : new HtmlString('');

        if ($editing && $block->container() && ($node['children'] ?? []) === []) {
            $children = new HtmlString('<div class="atlas-placeholder" data-atlas-placeholder>Drop blocks here</div>');
        }

        $customCode = (bool) config('atlas.custom_code');
        $domId = $customCode && ! empty($props['html_id'])
            ? (preg_replace('/[^A-Za-z0-9_-]/', '', (string) $props['html_id']) ?: 'atlas-'.$id)
            : 'atlas-'.$id;

        $node['id'] = $id;
        $node['dom_id'] = $domId;

        try {
            $inner = $block->render($props, $children, $node, $editing);
        } catch (Throwable $e) {
            report($e);
            $inner = $editing
                ? '<div class="atlas-error"><strong>'.e($block->label()).' failed:</strong> '.e($e->getMessage()).'</div>'
                : '';
        }

        if ($customCode && filled($props['custom_css'] ?? null)) {
            $inner .= '<style>'.self::scopeCss((string) $props['custom_css'], $domId).'</style>';
        }

        return $this->wrap($id, $type, trim('atlas-b-'.$type.' '.($props['css_class'] ?? '')), $block->container(), $inner, $node, $editing, $domId);
    }

    protected function wrap(string $id, string $type, string $class, bool $container, string $inner, array $node, bool $editing = true, ?string $domId = null): string
    {
        $attrs = 'id="'.e($domId ?? 'atlas-'.$id).'" class="atlas-block '.e($class).'"';

        if ($editing) {
            $attrs .= ' data-atlas-id="'.e($id).'" data-atlas-type="'.e($type).'"'.($container ? ' data-atlas-container="1"' : '');
        }

        return '<div '.$attrs.'>'.$inner.'</div>';
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

        $head = '';
        foreach ($this->atlas->styles() as $url) {
            $head .= '<link rel="stylesheet" href="'.e($url).'">'."\n";
        }
        if ($editing) {
            $head .= '<style>'.file_get_contents(__DIR__.'/../../resources/dist/canvas.css').'</style>'."\n";
        }
        if ($custom && filled($get('css'))) {
            $head .= '<style id="atlas-page-css">'.$get('css').'</style>'."\n";
        }
        if ($custom && filled($get('head'))) {
            $head .= $get('head')."\n";
        }

        $scripts = '';
        if (! $editing) {
            foreach ($this->atlas->scripts() as $script) {
                $scripts .= '<script src="'.e($script['src']).'"'.($script['defer'] ? ' defer' : '').'></script>'."\n";
            }
            if ($custom && filled($get('js'))) {
                $scripts .= '<script id="atlas-page-js">'.$get('js').'</script>'."\n";
            }
        }

        $description = $get('meta')['description'] ?? null;

        return [
            'page' => $page instanceof Page ? $page : (object) $page,
            'title' => $get('title', ''),
            'description' => $description,
            'content' => $this->render($get('content', []) ?? [], $editing),
            'head' => new HtmlString($head),
            'scripts' => new HtmlString($scripts),
            'editing' => $editing,
        ];
    }
}
