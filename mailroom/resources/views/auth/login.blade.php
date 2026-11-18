@extends('layouts.public')
@section('title', 'Log in')
@section('content')
    <h1><span class="brand-mark">✉</span> {{ config('app.name') }}</h1>
    @include('partials.flash')
    <form method="post" action="{{ url('/login') }}" class="stack">
        @csrf
        <label>Email <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
        <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
        <label class="check"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <button class="btn btn-primary">Log in</button>
    </form>
@endsection
