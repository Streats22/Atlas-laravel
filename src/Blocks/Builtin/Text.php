<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Block;
use Atlas\Blocks\Field;

class Text extends Block
{
    public function type(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Text';
    }

    public function icon(): string
    {
        return '¶';
    }

    public function category(): string
    {
        return 'Content';
    }

    public function view(): string
    {
        return 'atlas::blocks.text';
    }

    public function fields(): array
    {
        return [
            Field::textarea('text', 'Text', 'Write something wonderful. Line breaks are kept.'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'Align', 'left'),
            Field::color('color', 'Colour'),
            Field::number('size', 'Font size (px)', 17),
        ];
    }
}
