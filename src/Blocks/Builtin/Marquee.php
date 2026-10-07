<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Marquee extends BuiltinBlock
{
    protected string $type = 'marquee';

    protected string $label = 'Marquee';

    protected string $icon = '⇄';

    protected string $category = 'Animated';

    public function fields(): array
    {
        return [
            Field::repeater('items', [
                Field::t(Field::text('text', 'Text')),
            ], 'Items', [
                ['text' => 'Design'], ['text' => 'Development'], ['text' => 'Branding'], ['text' => 'Motion'],
            ], 'text'),
            Field::text('separator', 'Separator', '✦'),
            Field::number('speed', 'Duration of one loop (s)', 24),
            Field::select('direction', ['left' => 'Right to left', 'right' => 'Left to right'], 'Direction', 'left'),
            Field::checkbox('pause', 'Pause on hover', true),
            Field::select('size', ['md' => 'Medium', 'lg' => 'Large', 'xl' => 'Huge'], 'Text size', 'lg'),
        ];
    }
}
