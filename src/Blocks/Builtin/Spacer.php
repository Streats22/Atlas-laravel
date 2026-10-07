<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Spacer extends BuiltinBlock
{
    protected string $type = 'spacer';

    protected string $label = 'Spacer';

    protected string $icon = '↕';

    protected string $category = 'Layout';

    public function fields(): array
    {
        return [
            Field::number('height', 'Height (px)', 40),
            Field::number('height_mobile', 'Height on mobile (px)', 24),
        ];
    }
}
