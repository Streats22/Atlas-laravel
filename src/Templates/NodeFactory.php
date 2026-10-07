<?php

declare(strict_types=1);

namespace Atlas\Templates;

use Atlas\Atlas;

/** Builds block nodes (with their registered defaults) for page templates. */
final class NodeFactory
{
    private int $counter = 0;

    public function __construct(private readonly Atlas $atlas)
    {
    }

    /**
     * @param  array<string, mixed>  $props  Overrides for the block's default props
     * @param  list<array<string, mixed>>  $children  Explicit children (default children of the block are used when empty)
     */
    public function make(string $type, array $props = [], array $children = []): array
    {
        $template = $this->atlas->blocks()->get($type)->toDefinition()['template'];

        return [
            'id' => $this->nextId(),
            'type' => $type,
            'props' => array_merge((array) $template['props'], $props),
            'children' => $children ?: array_map(
                fn (array $child) => $this->make($child['type'], (array) ($child['props'] ?? [])),
                $template['children'],
            ),
        ];
    }

    /** A Section containing $children. */
    public function section(array $props = [], array $children = []): array
    {
        return $this->make('section', $props, $children);
    }

    /** A Columns block whose cells are padding-free sections holding the given children. */
    public function columns(array $cells, array $props = []): array
    {
        $cell = fn (array $children) => $this->section(['padding_y' => 0, 'max_width' => 'full'], $children);

        return $this->make('columns', $props, array_map($cell, $cells));
    }

    /** URL of one of the sample images shipped with Atlas (1–8). */
    public function image(int $n): string
    {
        return route('atlas.asset.demo', ['file' => "{$n}.jpg"], false);
    }

    private function nextId(): string
    {
        return 't' . str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT);
    }
}
