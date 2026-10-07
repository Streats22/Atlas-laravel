<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Block;
use Atlas\Blocks\Field;

class Heading extends Block
{
    public function type(): string
    {
        return 'heading';
    }

    public function label(): string
    {
        return 'Heading';
    }

    public function icon(): string
    {
        return 'H';
    }

    public function category(): string
    {
        return 'Content';
    }

    public function view(): string
    {
        return 'atlas::blocks.heading';
    }

    public function data(array $props): array
    {
        $level = in_array($props['level'] ?? '', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $props['level'] : 'h2';

        return ['tag' => $level];
    }

    public function fields(): array
    {
        return [
            Field::text('text', 'Text', 'A great headline'),
            Field::select('level', ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6'], 'Level', 'h2'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'Align', 'left'),
            Field::color('color', 'Colour'),
        ];
    }
}
