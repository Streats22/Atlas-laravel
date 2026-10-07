<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Heading extends BuiltinBlock
{
    protected string $type = 'heading';

    protected string $label = 'Heading';

    protected string $icon = 'H';

    protected string $category = 'Content';

    public function fields(): array
    {
        return [
            Field::t(Field::text('eyebrow', 'Eyebrow (small text above)', '')),
            Field::t(Field::text('text', 'Text', 'A great headline')),
            Field::select('level', ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6'], 'Level', 'h2'),
            Field::select('size', ['auto' => 'Automatic', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large', '2xl' => 'Display'], 'Size', 'auto'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'Align', 'left'),
            Field::color('color', 'Colour'),
            Field::checkbox('gradient', 'Gradient text'),
        ];
    }

    public function data(array $props): array
    {
        $level = in_array($props['level'] ?? '', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $props['level'] : 'h2';

        return ['tag' => $level];
    }
}
