<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SegmentRequest;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\Segment;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SegmentController extends Controller
{
    public function index(Audience $audience): View
    {
        $segments = $audience->segments()->orderBy('name')->get()
            ->each(fn (Segment $s) => $s->setAttribute('size', $s->contacts()->subscribed()->count()));

        return view('segments.index', ['audience' => $audience, 'segments' => $segments]);
    }

    public function create(Audience $audience): View
    {
        return view('segments.edit', [
            'audience' => $audience,
            'segment' => new Segment(['match' => 'all', 'conditions' => [['field' => 'email', 'operator' => 'contains', 'value' => '']]]),
        ]);
    }

    public function store(SegmentRequest $request, Audience $audience): RedirectResponse
    {
        $audience->segments()->create([
            'name' => $request->validated('name'),
            'match' => $request->validated('match'),
            'conditions' => $request->conditions(),
        ]);

        return redirect()->route('audiences.segments.index', $audience)->with('status', 'Segment saved.');
    }

    public function edit(Audience $audience, Segment $segment): View
    {
        return view('segments.edit', [
            'audience' => $audience,
            'segment' => $segment,
            'preview' => $segment->contacts()->subscribed()->limit(10)->get(),
            'size' => $segment->contacts()->subscribed()->count(),
        ]);
    }

    public function update(SegmentRequest $request, Audience $audience, Segment $segment): RedirectResponse
    {
        $segment->update([
            'name' => $request->validated('name'),
            'match' => $request->validated('match'),
            'conditions' => $request->conditions(),
        ]);

        return redirect()->route('audiences.segments.edit', [$audience, $segment])->with('status', 'Segment saved.');
    }

    public function destroy(Audience $audience, Segment $segment): RedirectResponse
    {
        // Deleting a segment would silently widen unsent campaigns to the whole audience.
        $inUse = Campaign::query()->where('segment_id', $segment->id)
            ->whereIn('status', ['draft', 'scheduled', 'sending', 'testing'])->pluck('name');
        if ($inUse->isNotEmpty()) {
            return back()->withErrors(['segment' => 'This segment is used by unsent campaigns: '.$inUse->implode(', ').'. Change those first.']);
        }
        $segment->delete();

        return redirect()->route('audiences.segments.index', $audience)->with('status', 'Segment deleted.');
    }
}
