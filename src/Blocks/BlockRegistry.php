<?php

namespace Atlas\Blocks;

use InvalidArgumentException;

class BlockRegistry
{
    /** @var array<string, Block> */
    protected array $blocks = [];

    public function register(Block|string $block): static
    {
        if (is_string($block)) {
            if (! is_subclass_of($block, Block::class)) {
                throw new InvalidArgumentException("[$block] must extend ".Block::class.'.');
            }
            $block = app($block);
        }

        $this->blocks[$block->type()] = $block;

        return $this;
    }

    public function has(string $type): bool
    {
        return isset($this->blocks[$type]);
    }

    public function get(string $type): ?Block
    {
        return $this->blocks[$type] ?? null;
    }

    /** @return array<string, Block> */
    public function all(): array
    {
        return $this->blocks;
    }

    public function definitions(): array
    {
        return array_values(array_map(fn (Block $b) => $b->toDefinition(), $this->blocks));
    }
}
