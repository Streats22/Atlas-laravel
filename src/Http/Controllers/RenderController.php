<?php

namespace Atlas\Http\Controllers;

use Atlas\Facades\Atlas;
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
        ]);

        $html = Atlas::renderer()->document([
            'title' => $data['title'] ?? '',
            'content' => Tree::sanitize($data['content']),
            'css' => $data['css'] ?? null,
            'head' => $data['head'] ?? null,
        ], editing: true);

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
