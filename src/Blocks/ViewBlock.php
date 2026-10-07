<?php

declare(strict_types=1);

namespace Atlas\Blocks;

/**
 * A block defined fluently without a class:
 *
 *   Atlas::viewBlock('hero', 'blocks.hero', fields: [Field::text('title')]);
 */
class ViewBlock extends Block
{
    public function __construct(
        protected string $type,
        protected string $view,
        protected ?string $label = null,
        protected array $fields = [],
        protected string $category = 'Custom',
        protected string $icon = '▢',
        protected bool $container = false,
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function view(): string
    {
        return $this->view;
    }

    public function label(): string
    {
        return $this->label ?? parent::label();
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function container(): bool
    {
        return $this->container;
    }
}
