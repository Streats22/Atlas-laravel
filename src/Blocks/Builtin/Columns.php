<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Block;
use Atlas\Blocks\Field;

class Columns extends Block
{
    public function type(): string
    {
        return 'columns';
    }

    public function label(): string
    {
        return 'Columns';
    }

    public function icon(): string
    {
        return '▥';
    }

    public function category(): string
    {
        return 'Layout';
    }

    public function view(): string
    {
        return 'atlas::blocks.columns';
    }

    public function container(): bool
    {
        return true;
    }

    public function defaultChildren(): array
    {
        return [
            ['type' => 'section', 'props' => ['padding' => 16, 'max_width' => 0, 'full_width' => true], 'children' => []],
            ['type' => 'section', 'props' => ['padding' => 16, 'max_width' => 0, 'full_width' => true], 'children' => []],
        ];
    }

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
            Field::number('gap', 'Gap (px)', 24),
            Field::select('align', ['stretch' => 'Stretch', 'start' => 'Top', 'center' => 'Middle', 'end' => 'Bottom'], 'Vertical align', 'stretch'),
            Field::checkbox('stack', 'Stack on mobile', true),
        ];
    }
}
