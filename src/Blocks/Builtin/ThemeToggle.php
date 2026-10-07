<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class ThemeToggle extends BuiltinBlock
{
    protected string $type = 'theme-toggle';

    protected string $label = 'Theme Toggle';

    protected string $icon = '◐';

    protected string $category = 'Utility';

    public function fields(): array
    {
        return [
            Field::select('style', ['icon' => 'Icon only', 'label' => 'Icon + label'], 'Style', 'label'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'Align', 'right'),
        ];
    }
}
