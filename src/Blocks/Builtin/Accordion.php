<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Accordion extends BuiltinBlock
{
    protected string $type = 'accordion';

    protected string $label = 'Accordion';

    protected string $icon = '☰';

    protected string $category = 'Layout';

    public function fields(): array
    {
        return [
            Field::repeater('items', [
                Field::t(Field::text('title', 'Title')),
                Field::t(Field::textarea('text', 'Content')),
            ], 'Items', [
                ['title' => 'What is Atlas?', 'text' => 'A visual page builder for Laravel.'],
                ['title' => 'Can I use my own code?', 'text' => 'Yes — custom blocks, HTML, CSS and JS.'],
            ], 'title'),
            Field::checkbox('open_first', 'Open first item', true),
            Field::checkbox('exclusive', 'Only one open at a time', true),
        ];
    }
}
