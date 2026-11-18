@extends('layouts.public')
@section('title', 'Subscribe')
@section('content')
    <h1>{{ $audience->name }}</h1>
    <p class="muted">Get our latest updates by email.</p>
    @include('partials.flash')
    <form method="post" action="{{ route('public.subscribe', $audience->public_id) }}" class="stack">
        <label>Email address <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
        <div class="grid-2">
            <label>First name <input name="first_name" value="{{ old('first_name') }}" autocomplete="given-name"></label>
            <label>Last name <input name="last_name" value="{{ old('last_name') }}" autocomplete="family-name"></label>
        </div>
        <div class="hp" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
        <button class="btn btn-primary">Subscribe</button>
        <p class="muted small">You can unsubscribe at any time with the link in every email.</p>
    </form>
@endsection
