<?php

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;
use Illuminate\Support\Str;

class ProjectShowcase extends BuiltinBlock
{
    protected string $type = 'project-showcase';

    protected string $label = 'Project Showcase';

    protected string $icon = '◧';

    protected string $category = 'Portfolio';

    public function fields(): array
    {
        return [
            Field::image('image', 'Image'),
            Field::t(Field::text('title', 'Title', 'Project name')),
            Field::t(Field::text('summary', 'Summary', 'One line that sells the project.')),
            Field::t(Field::textarea('description', 'Description (Markdown)', 'What the brief was, what you did and what changed because of it.')),
            Field::repeater('meta', [
                Field::t(Field::text('label', 'Label')),
                Field::t(Field::text('value', 'Value')),
            ], 'Details', [
                ['label' => 'Client', 'value' => 'Acme Inc.'],
                ['label' => 'Role', 'value' => 'Design & development'],
                ['label' => 'Year', 'value' => '2026'],
            ], 'label'),
            Field::text('tags', 'Tags (comma separated)', 'Laravel, Branding'),
            Field::url('url', 'Link'),
            Field::t(Field::text('url_label', 'Link label', 'View project')),
            Field::select('image_side', ['left' => 'Image left', 'right' => 'Image right'], 'Image side', 'left'),
        ];
    }

    public function data(array $props): array
    {
        return [
            'html' => Str::markdown((string) ($props['description'] ?? ''), ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string) ($props['tags'] ?? ''))))),
        ];
    }
}
