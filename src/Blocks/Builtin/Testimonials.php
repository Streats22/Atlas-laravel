<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Testimonials extends BuiltinBlock
{
    protected string $type = 'testimonials';

    protected string $label = 'Testimonials';

    protected string $icon = '❝';

    protected string $category = 'Portfolio';

    public function fields(): array
    {
        return [
            Field::repeater('items', [
                Field::t(Field::textarea('quote', 'Quote')),
                Field::text('name', 'Name'),
                Field::t(Field::text('role', 'Role / company')),
                Field::image('avatar', 'Avatar'),
            ], 'Testimonials', [
                ['quote' => 'Working together was effortless and the result exceeded expectations.', 'name' => 'Alex Morgan', 'role' => 'CEO, Acme', 'avatar' => ''],
                ['quote' => 'Thoughtful, fast and genuinely creative.', 'name' => 'Sam Rivera', 'role' => 'Founder, Northwind', 'avatar' => ''],
            ], 'name'),
            Field::select('layout', ['grid' => 'Grid', 'carousel' => 'Carousel'], 'Layout', 'grid'),
            Field::select('columns', ['1' => '1', '2' => '2', '3' => '3'], 'Columns (grid)', '2'),
        ];
    }
}
