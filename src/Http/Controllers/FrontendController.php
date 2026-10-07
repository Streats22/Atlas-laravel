<?php

declare(strict_types=1);

namespace Atlas\Http\Controllers;

use Atlas\Models\Page;
use Atlas\Support\Locales;
use Illuminate\Http\Request;

class FrontendController
{
    // Route parameters are passed positionally, so locale routes get their own methods.
    public function homeLocale(Request $request, string $locale)
    {
        return $this->home($request, $locale);
    }

    public function showLocale(Request $request, string $locale, string $slug)
    {
        return $this->show($request, $slug, $locale);
    }

    public function home(Request $request, ?string $locale = null)
    {
        Locales::resolve($request, $locale);

        $page = Page::published()->where('slug', config('atlas.frontend.home'))->first();

        // Nothing to show at "/": behave as if this route did not exist.
        abort_unless($page, 404);

        return $this->respond($request, $page);
    }

    public function show(Request $request, string $slug, ?string $locale = null)
    {
        Locales::resolve($request, $locale);

        return $this->respond($request, Page::published()->where('slug', $slug)->firstOrFail());
    }

    protected function respond(Request $request, Page $page)
    {
        $request->attributes->set('atlas.page', $page);

        return response($page->render())->header('Content-Type', 'text/html; charset=utf-8');
    }
}
