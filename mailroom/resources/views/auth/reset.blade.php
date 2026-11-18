@extends('layouts.public')
@section('title', 'Choose a password')
@section('content')
    <h1>Choose a password</h1>
    @include('partials.flash')
    <form method="post" action="{{ route('password.update') }}" class="stack">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username"></label>
        <label>New password <small class="muted">(at least 10 characters)</small><input type="password" name="password" required autocomplete="new-password" minlength="10"></label>
        <label>Confirm password <input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        <button class="btn btn-primary">Save password</button>
    </form>
@endsection
