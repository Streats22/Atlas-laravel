<?php

namespace Atlas\Http\Controllers;

use Atlas\Facades\Atlas;
use Atlas\Models\Page;
use Atlas\Support\Tree;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageController
{
    public function index()
    {
        return view('atlas::pages', ['pages' => Page::latest('updated_at')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $page = Page::create([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug(Str::slug($data['title']) ?: 'page'),
            'status' => 'draft',
            'content' => [],
        ]);

        return redirect()->route('atlas.pages.edit', $page);
    }

    public function edit(Page $page)
    {
        return view('atlas::editor', [
            'page' => $page,
            'config' => [
                'page' => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'status' => $page->status,
                    'content' => Tree::forClient($page->content ?? []),
                    'css' => $page->css,
                    'js' => $page->js,
                    'head' => $page->head,
                    'meta' => (object) ($page->meta ?? []),
                ],
                'blocks' => Atlas::blocks()->definitions(),
                'customCode' => (bool) config('atlas.custom_code'),
                'urls' => [
                    'save' => route('atlas.api.pages.update', $page),
                    'render' => route('atlas.api.render'),
                    'upload' => route('atlas.api.upload'),
                    'preview' => route('atlas.pages.preview', $page),
                    'pages' => route('atlas.index'),
                    'public' => $page->url(),
                ],
            ],
        ]);
    }

    public function update(Request $request, Page $page)
    {
        $reserved = trim((string) config('atlas.path'), '/');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:[\-\/][a-z0-9]+)*$/',
                Rule::unique($page->getTable(), 'slug')->ignore($page->id),
                function ($attr, $value, $fail) use ($reserved) {
                    if ($reserved !== '' && ($value === $reserved || str_starts_with($value, $reserved.'/'))) {
                        $fail('That slug is reserved for the Atlas editor.');
                    }
                },
            ],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'content' => ['present', 'array'],
            'css' => ['nullable', 'string'],
            'js' => ['nullable', 'string'],
            'head' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
            'meta.description' => ['nullable', 'string', 'max:500'],
        ]);

        $custom = (bool) config('atlas.custom_code');

        $page->fill([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'content' => Tree::sanitize($data['content']),
            'css' => $custom ? ($data['css'] ?? null) : $page->css,
            'js' => $custom ? ($data['js'] ?? null) : $page->js,
            'head' => $custom ? ($data['head'] ?? null) : $page->head,
            'meta' => ['description' => $data['meta']['description'] ?? null],
        ]);

        if ($page->status === 'published' && ! $page->published_at) {
            $page->published_at = now();
        }

        $page->save();

        return response()->json(['ok' => true, 'slug' => $page->slug, 'url' => $page->url(), 'saved_at' => now()->toIso8601String()]);
    }

    /** Full render of any page (drafts included) — custom JavaScript runs here. */
    public function preview(Page $page)
    {
        return response($page->render())->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function destroy(Page $page)
    {
        $page->delete();

        return redirect()->route('atlas.index');
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Page::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
