<?php

declare(strict_types=1);

namespace Atlas\Templates;

use InvalidArgumentException;

final class TemplateRegistry
{
    /** @var array<string, PageTemplate> */
    private array $templates = [];

    public function register(PageTemplate|string $template): self
    {
        $template = is_string($template) ? app($template) : $template;

        if (! $template instanceof PageTemplate) {
            throw new InvalidArgumentException('Page templates must extend ' . PageTemplate::class . '.');
        }

        $this->templates[$template->key()] = $template;

        return $this;
    }

    public function get(string $key): ?PageTemplate
    {
        return $this->templates[$key] ?? null;
    }

    /** @return array<string, PageTemplate> */
    public function all(): array
    {
        return $this->templates;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->templates);
    }
}
