<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Typewriter extends BuiltinBlock
{
    protected string $type = 'typewriter';

    protected string $label = 'Typewriter';

    protected string $icon = '⌨';

    protected string $category = 'Animated';

    public function fields(): array
    {
        return [
            Field::t(Field::text('prefix', 'Text before', 'I design')),
            Field::t(Field::textarea('words', 'Rotating words (one per line)', "brands\nwebsites\napps")),
            Field::t(Field::text('suffix', 'Text after', '.')),
            Field::select('tag', ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'p' => 'Paragraph'], 'Tag', 'h2'),
            Field::select('align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'Align', 'center'),
            Field::number('speed', 'Typing speed (ms per letter)', 70),
            Field::number('pause', 'Pause between words (ms)', 1400),
            Field::checkbox('loop', 'Loop', true),
        ];
    }

    public function data(array $props): array
    {
        $words = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($props['words'] ?? '')))));
        $tag = in_array($props['tag'] ?? '', ['h1', 'h2', 'h3', 'p'], true) ? $props['tag'] : 'h2';

        return ['wordList' => $words, 'tag' => $tag];
    }
}
