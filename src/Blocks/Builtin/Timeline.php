<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Timeline extends BuiltinBlock
{
    protected string $type = 'timeline';

    protected string $label = 'Timeline';

    protected string $icon = '⋮';

    protected string $category = 'Portfolio';

    public function fields(): array
    {
        return [
            Field::repeater('items', [
                Field::text('period', 'Period'),
                Field::t(Field::text('title', 'Title')),
                Field::t(Field::text('subtitle', 'Company / school')),
                Field::t(Field::textarea('text', 'Description')),
            ], 'Entries', [
                ['period' => '2024 – now', 'title' => 'Senior Designer', 'subtitle' => 'Studio North', 'text' => 'Leading product design across web and mobile.'],
                ['period' => '2020 – 2024', 'title' => 'Designer', 'subtitle' => 'Acme', 'text' => 'Shipped brand and product work for 30+ clients.'],
            ], 'title'),
            Field::select('style', ['line' => 'Single line', 'alternate' => 'Alternating'], 'Style', 'line'),
        ];
    }
}
