<?php

declare(strict_types=1);

namespace Atlas\Templates;

use Illuminate\Support\Facades\Lang;

/** A starting point for new pages. Extend it and register with Atlas::template(). */
abstract class PageTemplate
{
    /** Unique machine key, e.g. "landing". */
    abstract public function key(): string;

    /** @return list<array<string, mixed>> The block tree. */
    abstract public function tree(NodeFactory $nodes): array;

    public function label(): string
    {
        return $this->translate('label', ucfirst($this->key()));
    }

    public function description(): string
    {
        return $this->translate('description', '');
    }

    /** Page meta (theme, description, …) applied together with the tree. */
    public function meta(): array
    {
        return [];
    }

    private function translate(string $part, string $fallback): string
    {
        $key = "atlas::ui.template_{$this->key()}" . ($part === 'label' ? '' : '_desc');

        return Lang::has($key) ? __($key) : $fallback;
    }
}
