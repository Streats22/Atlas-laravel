<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Block;
use Atlas\Blocks\Field;

class Divider extends Block
{
    public function type(): string
    {
        return 'divider';
    }

    public function label(): string
    {
        return 'Divider';
    }

    public function icon(): string
    {
        return '—';
    }

    public function category(): string
    {
        return 'Layout';
    }

    public function view(): string
    {
        return 'atlas::blocks.divider';
    }

    public function fields(): array
    {
        return [
            Field::color('color', 'Colour', '#d1d5db'),
            Field::number('thickness', 'Thickness (px)', 1),
            Field::number('margin', 'Vertical margin (px)', 16),
        ];
    }
}
