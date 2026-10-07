<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Hero extends BuiltinBlock
{
    protected string $type = 'hero';

    protected string $label = 'Hero';

    protected string $icon = '★';

    protected string $category = 'Content';

    public function fields(): array
    {
        return [
            Field::t(Field::text('eyebrow', 'Eyebrow', 'Welcome')),
            Field::t(Field::text('title', 'Title', 'Build something people remember')),
            Field::t(Field::textarea('text', 'Text', 'A short, confident description of what you do and who it is for.')),
            Field::t(Field::text('primary_label', 'Primary button', 'Get started')),
            Field::url('primary_url', 'Primary link', '#'),
            Field::t(Field::text('secondary_label', 'Secondary button', 'Learn more')),
            Field::url('secondary_url', 'Secondary link', '#'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center'], 'Align', 'center'),
            Field::image('bg_image', 'Background image'),
            Field::number('overlay', 'Image overlay (%)', 45, ['min' => 0, 'max' => 90]),
            Field::checkbox('gradient', 'Animated gradient background', true),
            Field::number('min_height', 'Minimum height (px)', 460),
        ];
    }
}
