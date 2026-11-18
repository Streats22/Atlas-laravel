@extends('layouts.app')
@section('title', $segment->exists ? $segment->name : 'New segment')
@section('content')
    @include('audiences._nav')
    @php($rows = old('conditions', $segment->conditions ?? []))
    <form method="post" action="{{ $segment->exists ? route('audiences.segments.update', [$audience, $segment]) : route('audiences.segments.store', $audience) }}" class="card stack">
        @csrf
        @if ($segment->exists) @method('put') @endif
        <label>Segment name <input name="name" value="{{ old('name', $segment->name) }}" required maxlength="120"></label>
        <label>Contacts match
            <select name="match"><option value="all" @selected(old('match', $segment->match) === 'all')>all conditions</option><option value="any" @selected(old('match', $segment->match) === 'any')>any condition</option></select>
        </label>
        <div id="conditions" class="stack">
            @foreach ([...$rows, ['field' => '', 'operator' => 'is', 'value' => '']] as $i => $c)
                <div class="condition" @if ($c['field'] === '') data-template hidden @endif>
                    <select name="conditions[{{ $i }}][field]" aria-label="Field"><option value="">—</option>
                        @foreach (\App\Services\SegmentQuery::FIELDS as $k => $label)<option value="{{ $k }}" @selected($c['field'] === $k)>{{ $label }}</option>@endforeach
                        @foreach ($audience->fields as $f)<option value="field:{{ $f->key }}" @selected($c['field'] === 'field:' . $f->key)>{{ $f->label }}</option>@endforeach
                    </select>
                    <select name="conditions[{{ $i }}][operator]" aria-label="Operator">
                        @foreach (\App\Services\SegmentQuery::OPERATORS as $k => $label)<option value="{{ $k }}" @selected($c['operator'] === $k)>{{ $label }}</option>@endforeach
                    </select>
                    <input name="conditions[{{ $i }}][value]" value="{{ $c['value'] ?? '' }}" aria-label="Value" placeholder="value (dates: YYYY-MM-DD)">
                    <button type="button" class="btn btn-ghost" onclick="this.parentElement.remove()" aria-label="Remove condition">✕</button>
                </div>
            @endforeach
        </div>
        <div class="row">
            <button type="button" class="btn btn-ghost" id="add-condition">+ Add condition</button>
            <span class="grow"></span>
            <button class="btn btn-primary">Save segment</button>
        </div>
        <p class="muted small">For “Opened/Clicked any campaign” use operator <em>is</em> / <em>is not</em>; the value is ignored. Status values: {{ implode(', ', \App\Services\SegmentQuery::statuses()) }}.</p>
    </form>

    @isset($preview)
        <section class="card">
            <h2>{{ number_format($size) }} subscribed contacts match</h2>
            @foreach ($preview as $c)<div class="link-row">{{ $c->email }} <span class="muted">{{ $c->fullName() }}</span></div>@endforeach
        </section>
        <form method="post" action="{{ route('audiences.segments.destroy', [$audience, $segment]) }}" onsubmit="return confirm('Delete this segment?')">
            @csrf @method('delete') <button class="btn btn-ghost danger">Delete segment</button>
        </form>
    @endisset
@endsection
@push('scripts')
<script>
document.getElementById('add-condition').addEventListener('click', () => {
    const tpl = document.querySelector('#conditions [data-template]');
    const index = document.querySelectorAll('#conditions .condition').length + Date.now();
    const row = tpl.cloneNode(true);
    row.hidden = false;
    row.removeAttribute('data-template');
    row.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace(/conditions\[\d+\]/, 'conditions[' + index + ']'); });
    tpl.before(row);
});
</script>
@endpush
