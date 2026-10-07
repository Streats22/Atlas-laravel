<?php

declare(strict_types=1);

namespace Atlas\Http\Controllers;

use Atlas\Blocks\CommonFields;
use Atlas\Enums\PageStatus;
use Atlas\Enums\Spacing;
use Atlas\Facades\Atlas;
use Atlas\Http\Requests\SavePageRequest;
use Atlas\Models\CustomBlock;
use Atlas\Models\Page;
use Atlas\Support\Locales;
use Atlas\Support\Theme;
use Atlas\Support\Tree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController
{
    public function index(): View
    {
        return view('atlas::pages', ['pages' => Page::latest('updated_at')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $page = Page::create([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug(Str::slug($data['title']) ?: 'page'),
            'status' => PageStatus::Draft,
            'content' => [],
        ]);

        return redirect()->route('atlas.pages.edit', $page);
    }

    public function edit(Page $page): View
    {
        return view('atlas::editor', ['page' => $page, 'config' => $this->editorConfig($page)]);
    }

    public function update(SavePageRequest $request, Page $page): JsonResponse
    {
        $data = $request->validated();
        $custom = (bool) config('atlas.custom_code');

        $page->fill([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => $request->status(),
            'content' => Tree::sanitize($data['content']),
            'css' => $custom ? ($data['css'] ?? null) : $page->css,
            'js' => $custom ? ($data['js'] ?? null) : $page->js,
            'head' => $custom ? ($data['head'] ?? null) : $page->head,
            'meta' => $request->meta()->toArray(),
        ]);

        if ($page->status === PageStatus::Published && ! $page->published_at) {
            $page->published_at = now();
        }

        $page->save();

        return response()->json(['ok' => true, 'slug' => $page->slug, 'url' => $page->url(), 'saved_at' => now()->toIso8601String()]);
    }

    /** Full render of any page (drafts included) — custom JavaScript runs here. */
    public function preview(Request $request, Page $page): Response
    {
        Locales::apply($request->query('locale'));
        $request->attributes->set('atlas.page', $page);

        return response($page->render())->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('atlas.index');
    }

    /** Everything the editor SPA needs, serialised into the page. */
    private function editorConfig(Page $page): array
    {
        $custom = (bool) config('atlas.custom_code');

        return [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status->value,
                'content' => Tree::forClient($page->content ?? []),
                'css' => $page->css,
                'js' => $page->js,
                'head' => $page->head,
                'meta' => (object) ($page->meta ?? []),
            ],
            'blocks' => Atlas::blocks()->definitions(),
            'common' => CommonFields::definitions(),
            'customBlocks' => $custom ? CustomBlock::orderBy('label')->get()->map->toBuilder()->all() : [],
            'customCode' => $custom,
            'locales' => ['available' => (object) Locales::available(), 'default' => Locales::default()],
            'theme' => Theme::fromMeta($page->metaBag())->toArray(),
            'fonts' => array_keys(Theme::FONTS),
            'spacings' => Spacing::values(),
            'i18n' => trans('atlas::ui'),
            'uiLocale' => app()->getLocale(),
            'urls' => [
                'save' => route('atlas.api.pages.update', $page),
                'render' => route('atlas.api.render'),
                'upload' => route('atlas.api.upload'),
                'preview' => route('atlas.pages.preview', $page),
                'blocks' => route('atlas.api.blocks.store'),
                'blockBase' => url(config('atlas.path') . '/api/blocks'),
                'pages' => route('atlas.index'),
                'public' => $page->url(),
            ],
        ];
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        for ($i = 2; Page::where('slug', $slug)->exists(); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}
