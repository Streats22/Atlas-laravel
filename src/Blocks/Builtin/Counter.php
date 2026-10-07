<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Counter extends BuiltinBlock
{
    protected string $type = 'counter';

    protected string $label = 'Counters';

    protected string $icon = '№';

    protected string $category = 'Animated';

    public function fields(): array
    {
        return [
            Field::repeater('items', [
                Field::number('value', 'Value', 100),
                Field::text('prefix', 'Prefix'),
                Field::text('suffix', 'Suffix'),
                Field::t(Field::text('label', 'Label')),
            ], 'Counters', [
                ['value' => 120, 'prefix' => '', 'suffix' => '+', 'label' => 'Projects'],
                ['value' => 8, 'prefix' => '', 'suffix' => '', 'label' => 'Years'],
                ['value' => 99, 'prefix' => '', 'suffix' => '%', 'label' => 'Happy clients'],
            ], 'label'),
            Field::number('duration', 'Count-up time (ms)', 1800),
            Field::select('align', ['left' => 'Left', 'center' => 'Center'], 'Align', 'center'),
        ];
    }
}
