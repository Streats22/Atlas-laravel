<?php

namespace Atlas\Blocks;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Base class for every block. Extend it (or run `php artisan atlas:make-block`)
 * to add your own blocks with completely custom PHP + Blade.
 */
abstract class Block
{
    /** Unique machine name, e.g. "pricing-table". */
    abstract public function type(): string;

    public function label(): string
    {
        return Str::headline($this->type());
    }

    /** Group shown in the block palette. */
    public function category(): string
    {
        return 'Custom';
    }

    /** A short glyph (emoji or a few characters) for the palette. */
    public function icon(): string
    {
        return '▢';
    }

    /** Can other blocks be dropped inside this one? */
    public function container(): bool
    {
        return false;
    }

    /** Inspector fields, built with Atlas\Blocks\Field. */
    public function fields(): array
    {
        return [];
    }

    /** Child node templates created along with a new block: [['type'=>..,'props'=>[..]]]. */
    public function defaultChildren(): array
    {
        return [];
    }

    /** The Blade view used to render the block. */
    public function view(): string
    {
        return 'atlas.blocks.'.$this->type();
    }

    /** Default property values derived from the field definitions. */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->fields() as $field) {
            $defaults[$field['name']] = $field['default'] ?? null;
        }

        return $defaults;
    }

    /** Extra variables for the view. Props arrive already merged with defaults. */
    public function data(array $props): array
    {
        return [];
    }

    /**
     * Render the block. Override for full control; the default renders
     * view() with: $props, $children, $node, $id, $domId, $editing (+ data()).
     *
     * @param  array  $node  The raw node: id, type, props, children, dom_id
     */
    public function render(array $props, HtmlString $children, array $node, bool $editing): string
    {
        return view($this->view(), array_merge([
            'props' => $props,
            'children' => $children,
            'node' => $node,
            'id' => $node['id'] ?? null,
            'domId' => $node['dom_id'] ?? 'atlas-'.($node['id'] ?? ''),
            'editing' => $editing,
        ], $this->data($props)))->render();
    }

    /** Definition sent to the editor. */
    public function toDefinition(): array
    {
        $template = [
            'type' => $this->type(),
            'props' => (object) $this->defaults(),
            'children' => $this->defaultChildren(),
        ];

        return [
            'type' => $this->type(),
            'label' => $this->label(),
            'category' => $this->category(),
            'icon' => $this->icon(),
            'container' => $this->container(),
            'fields' => $this->fields(),
            'template' => $template,
        ];
    }
}
