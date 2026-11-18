<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContactStatus;
use App\Http\Requests\AudienceRequest;
use App\Models\Audience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AudienceController extends Controller
{
    public function index(): View
    {
        return view('audiences.index', [
            'audiences' => Audience::query()
                ->withCount(['contacts', 'contacts as subscribed_count' => fn (Builder $q) => $q->where('status', ContactStatus::Subscribed)])
                ->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('audiences.edit', ['audience' => new Audience(['double_opt_in' => true])]);
    }

    public function store(AudienceRequest $request): RedirectResponse
    {
        $audience = Audience::query()->create($request->validated());

        return redirect()->route('audiences.show', $audience)->with('status', 'Audience created.');
    }

    public function show(Request $request, Audience $audience): View
    {
        $search = trim((string) $request->query('q'));
        $status = ContactStatus::tryFrom((string) $request->query('status'));
        $tagId = $request->integer('tag') ?: null;

        $contacts = $audience->contacts()
            ->with('tags')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('email', "%{$search}%")
                ->orWhereLike('first_name', "%{$search}%")
                ->orWhereLike('last_name', "%{$search}%")))
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->when($tagId, fn (Builder $q) => $q->whereHas('tags', fn (Builder $t) => $t->whereKey($tagId)))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = collect(ContactStatus::cases())->mapWithKeys(fn (ContactStatus $s) => [
            $s->value => $audience->contacts()->where('status', $s)->count(),
        ]);

        return view('audiences.show', [
            'audience' => $audience,
            'contacts' => $contacts,
            'counts' => $counts,
            'tags' => $audience->tags()->withCount('contacts')->orderBy('name')->get(),
            'segments' => $audience->segments()->orderBy('name')->get(),
            'filters' => ['q' => $search, 'status' => $status?->value, 'tag' => $tagId],
        ]);
    }

    public function edit(Audience $audience): View
    {
        return view('audiences.edit', ['audience' => $audience]);
    }

    public function update(AudienceRequest $request, Audience $audience): RedirectResponse
    {
        $audience->update($request->validated());

        return redirect()->route('audiences.show', $audience)->with('status', 'Audience saved.');
    }

    public function destroy(Audience $audience): RedirectResponse
    {
        $audience->delete();

        return redirect()->route('audiences.index')->with('status', 'Audience deleted.');
    }

    public function form(Audience $audience): View
    {
        return view('audiences.form', ['audience' => $audience]);
    }
}
