<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Button extends BuiltinBlock
{
    protected string $type = 'button';

    protected string $label = 'Button';

    protected string $icon = '◉';

    protected string $category = 'Content';

    public function fields(): array
    {
        return [
            Field::t(Field::text('label', 'Label', 'Click me')),
            Field::url('url', 'Link', '#'),
            Field::select('style', ['solid' => 'Solid', 'outline' => 'Outline', 'ghost' => 'Ghost'], 'Style', 'solid'),
            Field::select('size', ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'], 'Size', 'md'),
            Field::color('color', 'Colour'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'Align', 'left'),
            Field::text('icon', 'Icon / emoji after label', ''),
            Field::checkbox('new_tab', 'Open in new tab'),
        ];
    }
}
