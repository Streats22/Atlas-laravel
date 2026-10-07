<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class SocialLinks extends BuiltinBlock
{
    protected string $type = 'social-links';

    protected string $label = 'Social Links';

    protected string $icon = '@';

    protected string $category = 'Content';

    public function fields(): array
    {
        return [
            Field::repeater('items', [
                Field::text('label', 'Label'),
                Field::url('url', 'URL'),
                Field::text('icon', 'Icon / short text'),
            ], 'Links', [
                ['label' => 'GitHub', 'url' => 'https://github.com', 'icon' => 'GH'],
                ['label' => 'LinkedIn', 'url' => 'https://linkedin.com', 'icon' => 'in'],
            ], 'label'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'Align', 'left'),
        ];
    }
}
