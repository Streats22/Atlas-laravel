<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Block;
use Atlas\Blocks\Field;

class Section extends Block
{
    public function type(): string
    {
        return 'section';
    }

    public function label(): string
    {
        return 'Section';
    }

    public function icon(): string
    {
        return '▭';
    }

    public function category(): string
    {
        return 'Layout';
    }

    public function view(): string
    {
        return 'atlas::blocks.section';
    }

    public function container(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            Field::color('background', 'Background'),
            Field::number('padding', 'Vertical padding (px)', 48),
            Field::number('max_width', 'Max width (px)', 1100),
            Field::checkbox('full_width', 'Full-width content'),
        ];
    }
}
