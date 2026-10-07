<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Block;

/** Shared plumbing for the blocks that ship with Atlas. */
abstract class BuiltinBlock extends Block
{
    protected string $type;

    protected string $label;

    protected string $icon = '▢';

    protected string $category = 'Content';

    protected bool $container = false;

    protected bool $fullBleed = false;

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        return $this->trans('label', $this->label);
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function container(): bool
    {
        return $this->container;
    }

    public function fullBleed(): bool
    {
        return $this->fullBleed;
    }

    public function view(): string
    {
        return 'atlas::blocks.' . $this->type;
    }
}
