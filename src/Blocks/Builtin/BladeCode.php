<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Block;
use Atlas\Blocks\Field;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class BladeCode extends Block
{
    public function type(): string
    {
        return 'blade';
    }

    public function label(): string
    {
        return 'Blade Code';
    }

    public function icon(): string
    {
        return '{}';
    }

    public function category(): string
    {
        return 'Developer';
    }

    public function view(): string
    {
        return 'atlas::blocks.blade';
    }

    public function fields(): array
    {
        return [
            Field::code('code', 'Blade template', '<p>&copy; {{ now()->year }}</p>', 'blade'),
        ];
    }

    /** Compiles the editor-written Blade. Registered only when atlas.allow_blade_code is true. */
    public function render(array $props, HtmlString $children, array $node, bool $editing): string
    {
        return Blade::render((string) ($props['code'] ?? ''), [
            'props' => $props,
            'node' => $node,
            'editing' => $editing,
        ]);
    }
}
