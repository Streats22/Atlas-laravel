<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Columns extends BuiltinBlock
{
    protected string $type = 'columns';

    protected string $label = 'Columns';

    protected string $icon = '▥';

    protected string $category = 'Layout';

    protected bool $container = true;

    public function fields(): array
    {
        return [
            Field::select('layout', [
                '1fr 1fr' => '2 equal columns',
                '1fr 2fr' => '1/3 + 2/3',
                '2fr 1fr' => '2/3 + 1/3',
                '1fr 1fr 1fr' => '3 equal columns',
                '1fr 1fr 1fr 1fr' => '4 equal columns',
            ], 'Layout', '1fr 1fr'),
            Field::number('gap', 'Gap (px)', 32),
            Field::select('align', ['stretch' => 'Stretch', 'start' => 'Top', 'center' => 'Middle', 'end' => 'Bottom'], 'Vertical align', 'stretch'),
            Field::checkbox('stack', 'Stack on mobile', true),
            Field::checkbox('reverse', 'Reverse order on mobile'),
        ];
    }

    public function defaultChildren(): array
    {
        $cell = ['type' => 'section', 'props' => ['padding_y' => 0, 'max_width' => 'full'], 'children' => []];

        return [$cell, $cell];
    }
}
