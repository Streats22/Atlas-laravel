@extends('layouts.public')
@section('title', 'Forgot password')
@section('content')
    <h1>Reset your password</h1>
    @include('partials.flash')
    <form method="post" action="{{ route('password.email') }}" class="stack">
        @csrf
        <label>Email <input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
        <button class="btn btn-primary">Send reset link</button>
        <a class="small" href="{{ route('login') }}">Back to log in</a>
    </form>
@endsection
