<?php

namespace Atlas\View\Components;

use Atlas\Facades\Atlas;
use Atlas\Models\Page as PageModel;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-atlas::page slug="about" /> embeds a published page (content, CSS and JS)
 * inside any of your own Blade views.
 */
class Page extends Component
{
    public function __construct(public ?string $slug = null, public ?PageModel $page = null) {}

    public function render(): View|string
    {
        $page = $this->page ?? PageModel::published()->where('slug', $this->slug)->first();

        if (! $page) {
            return '';
        }

        return view('atlas::components.page', Atlas::renderer()->layoutData($page));
    }
}
