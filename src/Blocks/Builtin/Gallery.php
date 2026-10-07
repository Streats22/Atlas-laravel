<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Gallery extends BuiltinBlock
{
    protected string $type = 'gallery';

    protected string $label = 'Gallery';

    protected string $icon = '▤';

    protected string $category = 'Portfolio';

    public function fields(): array
    {
        return [
            Field::repeater('items', [
                Field::image('image', 'Image'),
                Field::t(Field::text('alt', 'Alt text')),
                Field::t(Field::text('caption', 'Caption')),
            ], 'Images', [
                ['image' => '', 'alt' => 'Image one', 'caption' => ''],
                ['image' => '', 'alt' => 'Image two', 'caption' => ''],
                ['image' => '', 'alt' => 'Image three', 'caption' => ''],
            ], 'alt'),
            Field::select('columns', ['2' => '2', '3' => '3', '4' => '4', '5' => '5'], 'Columns', '3'),
            Field::number('gap', 'Gap (px)', 12),
            Field::select('ratio', ['1/1' => 'Square', '4/3' => '4:3', '16/9' => '16:9', 'auto' => 'Original'], 'Ratio', '1/1'),
            Field::checkbox('lightbox', 'Lightbox', true),
        ];
    }
}
