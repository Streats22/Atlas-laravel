<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Image extends BuiltinBlock
{
    protected string $type = 'image';

    protected string $label = 'Image';

    protected string $icon = '🖼';

    protected string $category = 'Content';

    public function fields(): array
    {
        return [
            Field::image('src', 'Image'),
            Field::t(Field::text('alt', 'Alt text')),
            Field::t(Field::text('caption', 'Caption')),
            Field::text('width', 'Width (e.g. 100% or 320px)', '100%'),
            Field::select('ratio', ['auto' => 'Original', '1/1' => 'Square', '4/3' => '4:3', '3/2' => '3:2', '16/9' => '16:9', '21/9' => 'Cinematic'], 'Aspect ratio', 'auto'),
            Field::select('fit', ['cover' => 'Cover', 'contain' => 'Contain'], 'Fit', 'cover'),
            Field::number('radius', 'Corner radius (px)', 0),
            Field::checkbox('shadow', 'Shadow'),
            Field::url('link', 'Link to'),
            Field::checkbox('lightbox', 'Open in lightbox'),
        ];
    }
}
