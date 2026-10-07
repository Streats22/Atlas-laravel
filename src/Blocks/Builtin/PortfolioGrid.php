<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class PortfolioGrid extends BuiltinBlock
{
    protected string $type = 'portfolio-grid';

    protected string $label = 'Portfolio Grid';

    protected string $icon = '▦';

    protected string $category = 'Portfolio';

    public function fields(): array
    {
        return [
            Field::repeater('items', [
                Field::image('image', 'Image'),
                Field::t(Field::text('title', 'Title')),
                Field::t(Field::text('category', 'Category')),
                Field::t(Field::textarea('description', 'Description')),
                Field::url('url', 'Project link'),
                Field::text('tags', 'Tags (comma separated)'),
            ], 'Projects', [
                ['image' => '', 'title' => 'Brand identity', 'category' => 'Branding', 'description' => 'Logo, colour and typography system.', 'url' => '', 'tags' => 'Logo, Print'],
                ['image' => '', 'title' => 'Marketing site', 'category' => 'Web', 'description' => 'A fast, accessible marketing site.', 'url' => '', 'tags' => 'Laravel, Design'],
                ['image' => '', 'title' => 'Mobile app', 'category' => 'App', 'description' => 'Concept and UI for a fitness app.', 'url' => '', 'tags' => 'UI, Prototype'],
            ], 'title'),
            Field::select('columns', ['2' => '2 columns', '3' => '3 columns', '4' => '4 columns'], 'Columns', '3'),
            Field::number('gap', 'Gap (px)', 20),
            Field::select('ratio', ['1/1' => 'Square', '4/3' => '4:3', '3/2' => '3:2', '16/9' => '16:9', '3/4' => 'Portrait'], 'Image ratio', '4/3'),
            Field::select('style', ['overlay' => 'Overlay on hover', 'caption' => 'Caption below', 'minimal' => 'Minimal'], 'Style', 'overlay'),
            Field::select('hover', ['zoom' => 'Zoom', 'lift' => 'Lift', 'none' => 'None'], 'Hover effect', 'zoom'),
            Field::checkbox('filter', 'Category filter bar', true),
            Field::t(Field::text('all_label', '“All” label', 'All')),
            Field::checkbox('lightbox', 'Open image in lightbox (when no link)', true),
        ];
    }

    public function data(array $props): array
    {
        $items = array_values(array_filter((array) ($props['items'] ?? []), 'is_array'));
        $categories = collect($items)->pluck('category')->filter()->unique()->values()->all();

        return ['items' => $items, 'categories' => $categories];
    }
}
