<?php

declare(strict_types=1);

namespace Atlas\Blocks;

use Atlas\Support\ViewHelpers;
use Illuminate\Support\Facades\Lang;
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
        return $this->trans('label', Str::headline($this->type()));
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

    /** Full-bleed blocks (sections, heroes) span the page width; others sit inside the page gutters. */
    public function fullBleed(): bool
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
        return 'atlas.blocks.' . $this->type();
    }

    /** Default property values derived from the field definitions. */
    public function defaults(): array
    {
        return Field::defaults($this->fields());
    }

    /**
     * Assets this block needs on any page that uses it:
     * ['styles' => ['https://…css'], 'scripts' => ['https://…js' | ['src' => …, 'defer' => true]]]
     */
    public function assets(): array
    {
        return [];
    }

    /** Look up a translated string for this block, falling back to $fallback. */
    protected function trans(string $key, string $fallback): string
    {
        $full = "atlas::blocks.{$this->type()}.{$key}";

        return Lang::has($full) ? __($full) : $fallback;
    }

    /** Extra variables for the view. Props arrive already merged with defaults and localized. */
    public function data(array $props): array
    {
        return [];
    }

    /**
     * Render the block. Override for full control; the default renders
     * view() with: $props, $children, $node, $id, $domId, $editing, $locale and the
     * helpers $safe(url), $pick(value, allowed, default), $int(value, default, min, max), $aspect(value),
     * $cssUrl(url), $cssColor(value), $cssLength(value, default) (+ data()).
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
            'safe' => ViewHelpers::safeUrl(...),
            'pick' => ViewHelpers::pick(...),
            'int' => ViewHelpers::int(...),
            'aspect' => ViewHelpers::ratio(...),
            'cssUrl' => ViewHelpers::cssUrl(...),
            'cssColor' => ViewHelpers::cssColor(...),
            'cssLength' => ViewHelpers::cssLength(...),
            'locale' => app()->getLocale(),
            'domId' => $node['dom_id'] ?? 'atlas-' . ($node['id'] ?? ''),
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
            'category' => Lang::has('atlas::categories.' . $this->category()) ? __('atlas::categories.' . $this->category()) : $this->category(),
            'icon' => $this->icon(),
            'container' => $this->container(),
            'fields' => array_map(fn ($f) => Field::localize($f, $this->type()), $this->fields()),
            'template' => $template,
        ];
    }
}
