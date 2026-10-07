<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class LanguageSwitcher extends BuiltinBlock
{
    protected string $type = 'language-switcher';

    protected string $label = 'Language Switcher';

    protected string $icon = '🌐';

    protected string $category = 'Utility';

    public function fields(): array
    {
        return [
            Field::select('style', ['inline' => 'Inline links', 'pills' => 'Pills'], 'Style', 'inline'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'Align', 'right'),
        ];
    }

    public function data(array $props): array
    {
        return ['links' => \Atlas\Support\Locales::links()];
    }
}
