<?php

declare(strict_types=1);

namespace Atlas;

use Atlas\Blocks\Block;
use Atlas\Blocks\BlockRegistry;
use Atlas\Blocks\ViewBlock;
use Atlas\Models\Page;
use Atlas\Rendering\Renderer;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class Atlas
{
    protected array $styles = [];

    protected array $scripts = [];

    protected ?Renderer $renderer = null;

    public function __construct(protected BlockRegistry $registry)
    {
    }

    public function blocks(): BlockRegistry
    {
        return $this->registry;
    }

    public function renderer(): Renderer
    {
        return $this->renderer ??= new Renderer($this);
    }

    /** Register a block class or instance. */
    public function block(Block|string $block): static
    {
        $this->registry->register($block);

        return $this;
    }

    /** Register a block backed only by a Blade view. */
    public function viewBlock(
        string $type,
        string $view,
        ?string $label = null,
        array $fields = [],
        string $category = 'Custom',
        string $icon = '▢',
        bool $container = false,
    ): static {
        return $this->block(new ViewBlock($type, $view, $label, $fields, $category, $icon, $container));
    }

    /** Load a stylesheet on every page and in the editor canvas. */
    public function style(string $url): static
    {
        $this->styles[] = $url;

        return $this;
    }

    /** Load a script on every public page. */
    public function script(string $url, bool $defer = false): static
    {
        $this->scripts[] = ['src' => $url, 'defer' => $defer];

        return $this;
    }

    public function styles(): array
    {
        return array_values(array_unique(array_merge(config('atlas.assets.styles', []), $this->styles)));
    }

    public function scripts(): array
    {
        $configured = array_map(fn ($src) => ['src' => $src, 'defer' => false], config('atlas.assets.scripts', []));

        return array_values(array_unique(array_merge($configured, $this->scripts), SORT_REGULAR));
    }

    /** Decide who may use the editor: Atlas::auth(fn ($user) => $user?->is_admin). */
    public function auth(Closure $callback): static
    {
        Gate::define('useAtlas', $callback);

        return $this;
    }

    /** Render a page body by slug, e.g. {!! Atlas::page('footer') !!} in any Blade view. */
    public function page(string $slug): HtmlString
    {
        $page = Page::published()->where('slug', $slug)->first();

        return $page ? $page->renderBody() : new HtmlString('');
    }
}
