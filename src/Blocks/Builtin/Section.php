<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Section extends BuiltinBlock
{
    protected string $type = 'section';

    protected string $label = 'Section';

    protected string $icon = '▭';

    protected string $category = 'Layout';

    protected bool $fullBleed = true;

    protected bool $container = true;

    public function fields(): array
    {
        return [
            Field::select('tone', ['none' => 'None', 'surface' => 'Surface', 'accent' => 'Accent', 'inverted' => 'Inverted'], 'Tone', 'none'),
            Field::color('background', 'Background colour'),
            Field::image('bg_image', 'Background image'),
            Field::number('overlay', 'Image overlay (%)', 0, ['min' => 0, 'max' => 90]),
            Field::checkbox('parallax', 'Parallax background'),
            Field::number('padding_y', 'Vertical padding (px) — empty = theme default', null, ['nullable' => true]),
            Field::select('max_width', ['narrow' => 'Narrow (720px)', 'normal' => 'Normal (1100px)', 'wide' => 'Wide (1400px)', 'full' => 'Full width'], 'Content width', 'normal'),
            Field::number('min_height', 'Minimum height (px)', 0),
            Field::select('valign', ['start' => 'Top', 'center' => 'Middle', 'end' => 'Bottom'], 'Vertical align', 'start'),
        ];
    }
}
