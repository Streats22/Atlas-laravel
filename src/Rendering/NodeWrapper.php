<?php

declare(strict_types=1);

namespace Atlas\Rendering;

use Atlas\Support\ViewHelpers;

/** Wraps a block's HTML in its container <div> (id, classes, animation + spacing attributes). */
final class NodeWrapper
{
    private const HIDE = ['hide-mobile', 'hide-desktop'];

    private const HOVER = ['lift', 'zoom', 'glow'];

    public function wrap(RenderNode $node, string $inner, bool $editing): string
    {
        $props = $node->props;
        $classes = ['atlas-block', 'atlas-b-' . $node->type];
        $attrs = ['id' => $node->domId];
        $style = '';

        if ($node->fullBleed) {
            $classes[] = 'atlas-fullbleed';
        }
        if (! empty($props['css_class'])) {
            $classes[] = $props['css_class'];
        }
        if (in_array($props['visibility'] ?? 'all', self::HIDE, true)) {
            $classes[] = 'atlas-' . $props['visibility'];
        }
        if (in_array($props['anim_hover'] ?? 'none', self::HOVER, true)) {
            $classes[] = 'atlas-hover-' . $props['anim_hover'];
        }

        $anim = (string) ($props['anim'] ?? 'none');
        if ($anim !== 'none' && preg_match('/^[a-z-]+$/', $anim)) {
            $attrs['data-atlas-anim'] = $anim;
            $style .= '--atlas-dur:' . ViewHelpers::int($props['anim_duration'] ?? null, 700) . 'ms;'
                . '--atlas-delay:' . ViewHelpers::int($props['anim_delay'] ?? null, 0) . 'ms;';
        }

        foreach (['margin_top' => 'margin-top', 'margin_bottom' => 'margin-bottom'] as $prop => $css) {
            if (($value = ViewHelpers::int($props[$prop] ?? null)) !== 0) {
                $style .= "{$css}:{$value}px;";
            }
        }

        if ($editing) {
            $attrs['data-atlas-id'] = $node->id;
            $attrs['data-atlas-type'] = $node->type;
            if ($node->container) {
                $attrs['data-atlas-container'] = '1';
            }
        }

        $attrs['class'] = implode(' ', $classes);
        if ($style !== '') {
            $attrs['style'] = $style;
        }

        return '<div' . $this->attributes($attrs) . '>' . $inner . '</div>';
    }

    /** Wrapper for a block type that is no longer registered (editor only). */
    public function unknown(string $id, string $type): string
    {
        return '<div id="atlas-' . e($id) . '" class="atlas-block atlas-unknown" data-atlas-id="' . e($id) . '" data-atlas-type="' . e($type) . '">'
            . '<div class="atlas-placeholder">' . e(__('atlas::ui.unknown_type')) . ' “' . e($type) . '”</div></div>';
    }

    private function attributes(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $name => $value) {
            $html .= ' ' . $name . '="' . e((string) $value) . '"';
        }

        return $html;
    }
}
