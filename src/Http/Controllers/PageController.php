<?php

namespace Atlas\Http\Controllers;

use Atlas\Facades\Atlas;
use Atlas\Blocks\CommonFields;
use Atlas\Models\CustomBlock;
use Atlas\Models\Page;
use Atlas\Support\Locales;
use Atlas\Support\Theme;
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
                'common' => CommonFields::definitions(),
                'customBlocks' => config('atlas.custom_code') ? CustomBlock::orderBy('label')->get()->map->toBuilder()->all() : [],
                'customCode' => (bool) config('atlas.custom_code'),
                'locales' => ['available' => (object) Locales::available(), 'default' => Locales::default()],
                'theme' => Theme::settings((array) $page->meta),
                'fonts' => array_keys(Theme::FONTS),
                'i18n' => trans('atlas::ui'),
                'uiLocale' => app()->getLocale(),
                'urls' => [
                    'save' => route('atlas.api.pages.update', $page),
                    'render' => route('atlas.api.render'),
                    'upload' => route('atlas.api.upload'),
                    'preview' => route('atlas.pages.preview', $page),
                    'blocks' => route('atlas.api.blocks.store'),
                    'blockBase' => url(config('atlas.path').'/api/blocks'),
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
            'meta.theme' => ['nullable', Rule::in(['auto', 'light', 'dark'])],
            'meta.theme_toggle' => ['nullable', 'boolean'],
            'meta.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'meta.accent_dark' => ['nullable', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'meta.font' => ['nullable', Rule::in(array_keys(Theme::FONTS))],
            'meta.heading_font' => ['nullable', Rule::in(array_merge(['same'], array_keys(Theme::FONTS)))],
            'meta.og_image' => ['nullable', 'string', 'max:500'],
            'meta.titles' => ['nullable', 'array'],
            'meta.titles.*' => ['nullable', 'string', 'max:255'],
            'meta.descriptions' => ['nullable', 'array'],
            'meta.descriptions.*' => ['nullable', 'string', 'max:500'],
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
            'meta' => $this->meta($data['meta'] ?? []),
        ]);

        if ($page->status === 'published' && ! $page->published_at) {
            $page->published_at = now();
        }

        $page->save();

        return response()->json(['ok' => true, 'slug' => $page->slug, 'url' => $page->url(), 'saved_at' => now()->toIso8601String()]);
    }

    /** Full render of any page (drafts included) — custom JavaScript runs here. */
    public function preview(Request $request, Page $page)
    {
        Locales::apply($request->query('locale'));
        $request->attributes->set('atlas.page', $page);

        return response($page->render())->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** Keep only the meta keys Atlas understands; drop empty values and unknown locales. */
    protected function meta(array $meta): array
    {
        $locales = array_keys(Locales::available());
        $clean = fn (array $map) => array_filter(
            array_intersect_key($map, array_flip($locales)),
            fn ($v) => is_string($v) && $v !== ''
        );

        $out = collect($meta)->only(['description', 'theme', 'theme_toggle', 'accent', 'accent_dark', 'font', 'heading_font', 'og_image'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        if (isset($out['theme_toggle'])) {
            $out['theme_toggle'] = (bool) $out['theme_toggle'];
        }
        if ($titles = $clean((array) ($meta['titles'] ?? []))) {
            $out['titles'] = $titles;
        }
        if ($descriptions = $clean((array) ($meta['descriptions'] ?? []))) {
            $out['descriptions'] = $descriptions;
        }

        return $out;
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
