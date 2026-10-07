<?php

declare(strict_types=1);

namespace Atlas\Http\Controllers;

use Atlas\Facades\Atlas;
use Atlas\Support\Locales;
use Atlas\Support\PageMeta;
use Atlas\Support\Tree;
use Illuminate\Http\Request;

/** Renders the (unsaved) editor state for the canvas. */
class RenderController
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['present', 'array'],
            'css' => ['nullable', 'string'],
            'head' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
            'locale' => ['nullable', 'string', 'max:12'],
        ]);

        $previous = app()->getLocale();
        Locales::apply($data['locale'] ?? null);

        try {
            $html = Atlas::renderer()->document([
                'title' => $data['title'] ?? '',
                'content' => Tree::sanitize($data['content']),
                'css' => $data['css'] ?? null,
                'head' => $data['head'] ?? null,
                'meta' => PageMeta::sanitize((array) ($data['meta'] ?? []))->toArray(),
            ], editing: true);
        } finally {
            app()->setLocale($previous);
        }

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
