<?php

declare(strict_types=1);

namespace Atlas\Blocks;

use InvalidArgumentException;

class BlockRegistry
{
    /** Valid block machine names (builder blocks, imports, tree nodes). */
    public const TYPE_PATTERN = '/^[a-z][a-z0-9-]{1,48}$/';

    /** @var array<string, Block> */
    protected array $blocks = [];

    /** @var list<\Closure> */
    protected array $loaders = [];

    /** Register blocks lazily (e.g. from the database) the first time the registry is used. */
    public function lazy(\Closure $loader): static
    {
        $this->loaders[] = $loader;

        return $this;
    }

    protected function boot(): void
    {
        $loaders = $this->loaders;
        $this->loaders = [];
        foreach ($loaders as $loader) {
            $loader($this);
        }
    }

    public function forget(string $type): void
    {
        unset($this->blocks[$type]);
    }

    public function register(Block|string $block): static
    {
        if (is_string($block)) {
            if (! is_subclass_of($block, Block::class) || (new \ReflectionClass($block))->isAbstract()) {
                throw new InvalidArgumentException("[$block] must be a concrete class extending " . Block::class . '.');
            }
            $block = app($block);
        }

        $this->blocks[$block->type()] = $block;

        return $this;
    }

    public function has(string $type): bool
    {
        $this->boot();

        return isset($this->blocks[$type]);
    }

    public function get(string $type): ?Block
    {
        $this->boot();

        return $this->blocks[$type] ?? null;
    }

    /** @return array<string, Block> */
    public function all(): array
    {
        $this->boot();

        return $this->blocks;
    }

    public function definitions(): array
    {
        return array_values(array_map(fn (Block $b) => $b->toDefinition(), $this->all()));
    }
}
