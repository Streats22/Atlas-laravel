<?php

declare(strict_types=1);

namespace Atlas\Rendering;

use Atlas\Atlas;
use Atlas\Blocks\Block;
use Atlas\Blocks\CommonFields;
use Atlas\Models\Page;
use Atlas\Support\Locales;
use Illuminate\Support\HtmlString;
use Throwable;

/** Turns a block tree into HTML, and a page into a full document. */
class Renderer
{
    private readonly PropLocalizer $localizer;

    private readonly NodeWrapper $wrapper;

    private readonly DocumentBuilder $documents;

    public function __construct(private readonly Atlas $atlas)
    {
        $this->localizer = new PropLocalizer();
        $this->wrapper = new NodeWrapper();
        $this->documents = new DocumentBuilder($atlas);
    }

    /** Replace the {{selector}} placeholder with the block's DOM id selector. */
    public static function scopeCss(string $css, string $domId): string
    {
        return str_replace('{{selector}}', '#' . $domId, $css);
    }

    /** Render a block tree to HTML. */
    public function render(array $nodes, bool $editing = false, ?AssetBag $assets = null): HtmlString
    {
        $assets ??= new AssetBag();
        $html = '';

        foreach ($nodes as $node) {
            if (is_array($node)) {
                $html .= $this->renderNode($node, $editing, $assets);
            }
        }

        return new HtmlString($html);
    }

    public function localize(array $fields, array $props, string $locale, string $default): array
    {
        return $this->localizer->localize($fields, $props, $locale, $default);
    }

    /** Assemble the document (via the configured layout) for a page. */
    public function document(Page|array $page, bool $editing = false): string
    {
        return view(config('atlas.layout', 'atlas::layouts.document'), $this->layoutData($page, $editing))->render();
    }

    /** Variables handed to the layout view. */
    public function layoutData(Page|array $page, bool $editing = false): array
    {
        $data = PageData::from($page);
        $assets = new AssetBag();
        $body = $this->render($data->content, $editing, $assets);

        return $this->documents->build($data, $body, $assets, $editing);
    }

    private function renderNode(array $node, bool $editing, AssetBag $assets): string
    {
        $type = (string) ($node['type'] ?? '');
        $id = RenderNode::cleanId($node['id'] ?? '') ?: bin2hex(random_bytes(4));
        $block = $this->atlas->blocks()->get($type);

        if (! $block) {
            return $editing ? $this->wrapper->unknown($id, $type) : '';
        }

        $props = $this->resolveProps($block, (array) ($node['props'] ?? []));
        $domId = $this->domId($id, $props);
        $children = $this->children($block, $node, $editing, $assets);

        $node['id'] = $id;
        $node['dom_id'] = $domId;

        try {
            $inner = $block->render($props, $children, $node, $editing);
            $assets->merge($block->assets(), ! $editing);
        } catch (Throwable $e) {
            report($e);
            $inner = $editing
                ? '<div class="atlas-error"><strong>' . e($block->label()) . ' failed:</strong> ' . e($e->getMessage()) . '</div>'
                : '';
        }

        if (config('atlas.custom_code') && filled($props['custom_css'] ?? null)) {
            $inner .= '<style>' . self::scopeCss((string) $props['custom_css'], $domId) . '</style>';
        }

        return $this->wrapper->wrap(
            new RenderNode($id, $type, $domId, $props, $block->container(), $block->fullBleed()),
            $inner,
            $editing,
        );
    }

    /** Defaults ← common props ← stored props, then pick the current locale's text. */
    private function resolveProps(Block $block, array $stored): array
    {
        $props = array_merge($block->defaults(), CommonFields::defaults(), $stored);

        return $this->localizer->localize($block->fields(), $props, Locales::current(), Locales::default());
    }

    private function domId(string $id, array $props): string
    {
        $custom = config('atlas.custom_code') && ! empty($props['html_id'])
            ? RenderNode::cleanId($props['html_id'])
            : '';

        return $custom !== '' ? $custom : 'atlas-' . $id;
    }

    private function children(Block $block, array $node, bool $editing, AssetBag $assets): HtmlString
    {
        if (! $block->container()) {
            return new HtmlString('');
        }

        if ($editing && ($node['children'] ?? []) === []) {
            return new HtmlString('<div class="atlas-placeholder" data-atlas-placeholder>' . e(__('atlas::ui.drop_here')) . '</div>');
        }

        return $this->render($node['children'] ?? [], $editing, $assets);
    }
}
