<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Divider extends BuiltinBlock
{
    protected string $type = 'divider';

    protected string $label = 'Divider';

    protected string $icon = '—';

    protected string $category = 'Layout';

    public function fields(): array
    {
        return [
            Field::select('style', ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'gradient' => 'Gradient'], 'Style', 'solid'),
            Field::color('color', 'Colour'),
            Field::number('thickness', 'Thickness (px)', 1),
            Field::number('width', 'Width (%)', 100, ['min' => 5, 'max' => 100]),
            Field::number('margin', 'Vertical margin (px)', 16),
        ];
    }
}
