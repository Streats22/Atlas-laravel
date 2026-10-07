<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Logos extends BuiltinBlock
{
    protected string $type = 'logos';

    protected string $label = 'Client Logos';

    protected string $icon = '◈';

    protected string $category = 'Portfolio';

    public function fields(): array
    {
        return [
            Field::repeater('items', [
                Field::image('image', 'Logo'),
                Field::text('name', 'Name'),
                Field::url('url', 'Link'),
            ], 'Logos', [
                ['image' => '', 'name' => 'Acme', 'url' => ''],
                ['image' => '', 'name' => 'Northwind', 'url' => ''],
                ['image' => '', 'name' => 'Globex', 'url' => ''],
                ['image' => '', 'name' => 'Initech', 'url' => ''],
            ], 'name'),
            Field::number('height', 'Logo height (px)', 40),
            Field::checkbox('grayscale', 'Grayscale until hover', true),
            Field::checkbox('marquee', 'Scrolling marquee'),
        ];
    }
}
