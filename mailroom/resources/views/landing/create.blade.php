@extends('layouts.app')
@section('title', 'Create landing page')
@section('content')
    <header class="page-head"><div><a class="muted small" href="{{ route('landing-pages.index') }}">← Landing pages</a><h1>Create landing page</h1></div></header>
    <form method="post" action="{{ route('landing-pages.store') }}" class="card stack narrow">
        @csrf
        <label>Page title <input name="title" value="{{ old('title') }}" required maxlength="150" placeholder="Join our newsletter"></label>
        <label>Signups go to audience
            <select name="audience_id">@foreach ($audiences as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
        </label>
        <div class="row"><button class="btn btn-primary">Create and design</button></div>
    </form>
@endsection
