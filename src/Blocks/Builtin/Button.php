<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Block;
use Atlas\Blocks\Field;

class Button extends Block
{
    public function type(): string
    {
        return 'button';
    }

    public function label(): string
    {
        return 'Button';
    }

    public function icon(): string
    {
        return '◉';
    }

    public function category(): string
    {
        return 'Content';
    }

    public function view(): string
    {
        return 'atlas::blocks.button';
    }

    public function data(array $props): array
    {
        return ['href' => \Atlas\Support\Url::safe($props['url'] ?? '#') ?: '#'];
    }

    public function fields(): array
    {
        return [
            Field::text('label', 'Label', 'Click me'),
            Field::url('url', 'Link', '#'),
            Field::select('style', ['solid' => 'Solid', 'outline' => 'Outline'], 'Style', 'solid'),
            Field::color('color', 'Colour', '#4f46e5'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'Align', 'left'),
            Field::checkbox('new_tab', 'Open in new tab'),
        ];
    }
}
