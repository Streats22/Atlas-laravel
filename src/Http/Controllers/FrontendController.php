<?php

namespace Atlas\Http\Controllers;

use Atlas\Models\Page;

class FrontendController
{
    public function home()
    {
        $page = Page::published()->where('slug', config('atlas.frontend.home'))->first();

        // Nothing to show at "/": behave as if this route did not exist.
        abort_unless($page, 404);

        return response($page->render());
    }

    public function show(string $slug)
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();

        return response($page->render());
    }
}
