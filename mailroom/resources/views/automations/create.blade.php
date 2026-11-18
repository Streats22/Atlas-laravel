@extends('layouts.app')
@section('title', 'Create automation')
@section('content')
    <header class="page-head"><div><a class="muted small" href="{{ route('automations.index') }}">← Automations</a><h1>Create automation</h1></div></header>
    @if ($audiences->isEmpty())
        <section class="card"><p class="empty"><a href="{{ route('audiences.create') }}">Create an audience</a> first.</p></section>
    @else
        <form method="post" action="{{ route('automations.store') }}" class="card stack narrow">
            @csrf
            <fieldset class="presets">
                <legend>Start from</legend>
                @foreach ($presets as $key => $preset)
                    <label class="preset"><input type="radio" name="preset" value="{{ $key }}" data-trigger="{{ $preset['trigger'] }}" @checked(old('preset', 'welcome') === $key)>
                        <span><strong>{{ $preset['label'] }}</strong><small class="muted">{{ count($preset['steps']) ? collect($preset['steps'])->map(fn ($s) => $s[0] === 'email' ? 'email' : 'wait ' . $s[1] . 'd')->implode(' → ') : 'Empty journey' }}</small></span></label>
                @endforeach
            </fieldset>
            <label>Name <input name="name" value="{{ old('name', 'Welcome new subscribers') }}" required maxlength="150"></label>
            <label>Audience
                <select name="audience_id">@foreach ($audiences as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
            </label>
            @include('automations._trigger_fields', ['automation' => null])
            <div class="row"><button class="btn btn-primary">Create</button></div>
        </form>
    @endif
@endsection
@push('scripts')<script src="{{ asset('js/automations.js') }}"></script>@endpush
