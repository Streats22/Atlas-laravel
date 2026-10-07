<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Block;
use Atlas\Blocks\Field;

class Spacer extends Block
{
    public function type(): string
    {
        return 'spacer';
    }

    public function label(): string
    {
        return 'Spacer';
    }

    public function icon(): string
    {
        return '↕';
    }

    public function category(): string
    {
        return 'Layout';
    }

    public function view(): string
    {
        return 'atlas::blocks.spacer';
    }

    public function fields(): array
    {
        return [
            Field::number('height', 'Height (px)', 40),
        ];
    }
}
