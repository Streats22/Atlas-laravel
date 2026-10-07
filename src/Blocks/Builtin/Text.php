<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;
use Illuminate\Support\Str;

class Text extends BuiltinBlock
{
    protected string $type = 'text';

    protected string $label = 'Text';

    protected string $icon = '¶';

    protected string $category = 'Content';

    public function fields(): array
    {
        return [
            Field::t(Field::textarea('text', 'Text (Markdown supported)', 'Write something **wonderful**. Markdown works: *italic*, [links](https://example.com), lists…')),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right', 'justify' => 'Justify'], 'Align', 'left'),
            Field::select('size', ['sm' => 'Small', 'md' => 'Normal', 'lg' => 'Large', 'xl' => 'Lead'], 'Size', 'md'),
            Field::color('color', 'Colour'),
            Field::checkbox('readable', 'Readable line length', true),
        ];
    }

    public function data(array $props): array
    {
        return ['html' => Str::markdown((string) ($props['text'] ?? ''), ['html_input' => 'strip', 'allow_unsafe_links' => false])];
    }
}
