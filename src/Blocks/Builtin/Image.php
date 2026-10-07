<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Block;
use Atlas\Blocks\Field;

class Image extends Block
{
    public function type(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return 'Image';
    }

    public function icon(): string
    {
        return '🖼';
    }

    public function category(): string
    {
        return 'Content';
    }

    public function view(): string
    {
        return 'atlas::blocks.image';
    }

    public function data(array $props): array
    {
        return [
            'src' => \Atlas\Support\Url::safe($props['src'] ?? ''),
            'href' => \Atlas\Support\Url::safe($props['link'] ?? ''),
        ];
    }

    public function fields(): array
    {
        return [
            Field::image('src', 'Image'),
            Field::text('alt', 'Alt text'),
            Field::text('width', 'Width (e.g. 100% or 320px)', '100%'),
            Field::number('radius', 'Corner radius (px)', 0),
            Field::url('link', 'Link to'),
        ];
    }
}
