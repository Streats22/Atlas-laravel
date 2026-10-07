<?php

declare(strict_types=1);

namespace Atlas\Rendering;

/** Collects the styles/scripts requested by the blocks rendered on one page. */
final class AssetBag
{
    /** @var array<string, string> */
    private array $styles = [];

    /** @var array<string, array{src: string, defer: bool}> */
    private array $scripts = [];

    /**
     * @param  array{styles?: list<string>, scripts?: list<string|array{src: string, defer?: bool}>}  $assets
     */
    public function merge(array $assets, bool $includeScripts = true): void
    {
        foreach ((array) ($assets['styles'] ?? []) as $url) {
            $this->styles[$url] = $url;
        }

        if (! $includeScripts) {
            return;
        }

        foreach ((array) ($assets['scripts'] ?? []) as $script) {
            $script = is_array($script) ? $script : ['src' => $script];
            if (filled($script['src'] ?? null)) {
                $this->scripts[$script['src']] = ['src' => $script['src'], 'defer' => (bool) ($script['defer'] ?? false)];
            }
        }
    }

    /** @return list<string> */
    public function styles(): array
    {
        return array_values($this->styles);
    }

    /** @return list<array{src: string, defer: bool}> */
    public function scripts(): array
    {
        return array_values($this->scripts);
    }
}
