<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class BladeCode extends BuiltinBlock
{
    protected string $type = 'blade';

    protected string $label = 'Blade Code';

    protected string $icon = '{}';

    protected string $category = 'Developer';

    public function fields(): array
    {
        return [
            Field::code('code', 'Blade template', '<p>&copy; {{ now()->year }}</p>', 'blade'),
        ];
    }

    /** Compiles editor-written Blade. Registered only when atlas.allow_blade_code is true. */
    public function render(array $props, HtmlString $children, array $node, bool $editing): string
    {
        return Blade::render((string) ($props['code'] ?? ''), [
            'props' => $props,
            'node' => $node,
            'editing' => $editing,
        ]);
    }
}
