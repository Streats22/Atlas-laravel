<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Builder\DesignRenderer;
use App\Builder\EmailDesign;
use App\Models\Audience;
use App\Models\LandingPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function __construct(private readonly EmailDesign $design, private readonly DesignRenderer $renderer) {}

    public function index(): View
    {
        return view('landing.index', ['pages' => LandingPage::query()->with('audience')->latest()->get()]);
    }

    public function create(): View|RedirectResponse
    {
        $audiences = Audience::query()->orderBy('name')->get();
        if ($audiences->isEmpty()) {
            return redirect()->route('audiences.create')->with('status', 'Create an audience first.');
        }

        return view('landing.create', ['audiences' => $audiences]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'audience_id' => ['required', 'exists:audiences,id'],
        ]);
        $slug = Str::slug($data['title']) ?: 'page';
        for ($base = $slug, $i = 2; LandingPage::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }
        $design = $this->design->starter(landing: true);
        $design['blocks'][0]['props']['text'] = $data['title'];
        $page = LandingPage::query()->create($data + ['slug' => $slug, 'design' => $design, 'html' => '']);
        $this->rerender($page);

        return redirect()->route('landing-pages.edit', $page)->with('status', 'Page created. Design it, then publish.');
    }

    public function edit(LandingPage $landingPage): View
    {
        return view('landing.edit', [
            'page' => $landingPage,
            'blockTypes' => EmailDesign::blocks(landing: true),
            'blockDefaults' => collect(EmailDesign::blocks(landing: true))->keys()->mapWithKeys(fn ($t) => [$t => $this->design->defaults($t)]),
            'socialNetworks' => EmailDesign::SOCIAL,
            'mergeTags' => [],
        ]);
    }

    public function update(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('landing_pages', 'slug')->ignore($landingPage->id)],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'tag' => ['nullable', 'string', 'max:60'],
            'published' => ['boolean'],
            'design' => ['required', 'json', 'max:1000000'],
        ]);
        $data['published'] = $request->boolean('published');
        $data['design'] = $this->design->normalize(json_decode($data['design'], true), landing: true);
        $landingPage->fill($data);
        $this->rerender($landingPage);

        return back()->with('status', $landingPage->published ? 'Page saved and live.' : 'Page saved (not published).');
    }

    public function destroy(LandingPage $landingPage): RedirectResponse
    {
        $landingPage->delete();

        return redirect()->route('landing-pages.index')->with('status', 'Page deleted.');
    }

    /** Editors can look at unpublished pages; visits are not counted. */
    public function preview(LandingPage $landingPage): Response
    {
        return $this->respond($landingPage);
    }

    public function show(string $slug): Response
    {
        $page = LandingPage::query()->where('slug', $slug)->where('published', true)->firstOrFail();
        $page->increment('visits');

        return $this->respond($page);
    }

    private function rerender(LandingPage $page): void
    {
        $page->loadMissing('audience');
        $page->html = $this->renderer->render($page->design, page: [
            'form_action' => route('public.subscribe', $page->audience->public_id),
            'landing_id' => $page->id ?? 0,
            'title' => $page->title,
            'description' => (string) $page->meta_description,
        ]);
        $page->save();
        if (! str_contains($page->html, 'name="landing" value="'.$page->id.'"') && str_contains($page->html, 'name="landing"')) {
            $this->rerender($page); // First save had no id yet.
        }
    }

    private function respond(LandingPage $page): Response
    {
        return response($page->html)
            ->header('Content-Security-Policy', "script-src 'none'; frame-ancestors 'self'")
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
