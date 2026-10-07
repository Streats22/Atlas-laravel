<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Carousel extends BuiltinBlock
{
    protected string $type = 'carousel';

    protected string $label = 'Carousel';

    protected string $icon = '◫';

    protected string $category = 'Animated';

    public function fields(): array
    {
        return [
            Field::repeater('slides', [
                Field::image('image', 'Image'),
                Field::t(Field::text('title', 'Title')),
                Field::t(Field::text('text', 'Text')),
                Field::url('url', 'Link'),
            ], 'Slides', [
                ['image' => '', 'title' => 'First slide', 'text' => 'Tell a short story.', 'url' => ''],
                ['image' => '', 'title' => 'Second slide', 'text' => 'Show another highlight.', 'url' => ''],
                ['image' => '', 'title' => 'Third slide', 'text' => 'Finish with a call to action.', 'url' => ''],
            ], 'title'),
            Field::number('height', 'Height (px)', 420),
            Field::checkbox('autoplay', 'Autoplay', true),
            Field::number('interval', 'Autoplay interval (ms)', 5000),
            Field::checkbox('arrows', 'Arrows', true),
            Field::checkbox('dots', 'Dots', true),
        ];
    }
}
