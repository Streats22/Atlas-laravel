<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Skills extends BuiltinBlock
{
    protected string $type = 'skills';

    protected string $label = 'Skills';

    protected string $icon = '▰';

    protected string $category = 'Portfolio';

    public function fields(): array
    {
        return [
            Field::repeater('items', [
                Field::t(Field::text('name', 'Skill')),
                Field::number('level', 'Level (%)', 80, ['min' => 0, 'max' => 100]),
            ], 'Skills', [
                ['name' => 'Design', 'level' => 90],
                ['name' => 'Laravel', 'level' => 85],
                ['name' => 'Motion', 'level' => 70],
            ], 'name'),
            Field::checkbox('show_percent', 'Show percentage', true),
        ];
    }
}
