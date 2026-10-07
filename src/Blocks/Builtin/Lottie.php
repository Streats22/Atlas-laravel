<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Lottie extends BuiltinBlock
{
    protected string $type = 'lottie';

    protected string $label = 'Lottie Animation';

    protected string $icon = '☄';

    protected string $category = 'Animated';

    public function fields(): array
    {
        return [
            Field::url('src', 'Lottie JSON URL'),
            Field::checkbox('autoplay', 'Autoplay', true),
            Field::checkbox('loop', 'Loop', true),
            Field::text('width', 'Width', '320px'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'Align', 'center'),
        ];
    }

    public function assets(): array
    {
        return ['scripts' => [['src' => config('atlas.cdn.lottie'), 'defer' => true]]];
    }
}
