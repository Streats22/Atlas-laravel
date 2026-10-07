<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class IconBox extends BuiltinBlock
{
    protected string $type = 'icon-box';

    protected string $label = 'Feature';

    protected string $icon = '✦';

    protected string $category = 'Content';

    public function fields(): array
    {
        return [
            Field::text('icon', 'Icon / emoji', '⚡'),
            Field::t(Field::text('title', 'Title', 'Fast by default')),
            Field::t(Field::textarea('text', 'Text', 'Describe the feature in a sentence or two.')),
            Field::url('link', 'Link'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center'], 'Align', 'left'),
            Field::checkbox('card', 'Show as card', true),
        ];
    }
}
