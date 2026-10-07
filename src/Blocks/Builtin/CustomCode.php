<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;
use Atlas\Rendering\Renderer;
use Illuminate\Support\HtmlString;

class CustomCode extends BuiltinBlock
{
    protected string $type = 'custom-code';

    protected string $label = 'Custom Code';

    protected string $icon = '</>';

    protected string $category = 'Developer';

    public function fields(): array
    {
        return [
            Field::code('html', 'HTML', '<div class="hello">Hello from custom code</div>', 'html'),
            Field::code('css', 'CSS  (use {{selector}} for this block)', '{{selector}} .hello { padding: 1rem; }', 'css'),
            Field::code('js', 'JavaScript  (variable `el` is this block)', '', 'js'),
            Field::checkbox('isolate', 'Isolate JavaScript in its own scope', true),
        ];
    }

    public function render(array $props, HtmlString $children, array $node, bool $editing): string
    {
        $domId = $node['dom_id'] ?? 'atlas-' . ($node['id'] ?? '');
        $props['css'] = Renderer::scopeCss((string) ($props['css'] ?? ''), $domId);

        return parent::render($props, $children, $node, $editing);
    }
}
