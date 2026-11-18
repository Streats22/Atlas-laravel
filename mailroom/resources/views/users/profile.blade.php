@extends('layouts.app')
@section('title', 'Profile')
@section('content')
    <header class="page-head"><h1>Your profile</h1></header>
    <form method="post" action="{{ route('profile.update') }}" class="card stack narrow">
        @csrf @method('put')
        <label>Name <input name="name" value="{{ old('name', $user->name) }}" required></label>
        <label>Email <input type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username"></label>
        <p class="muted small">Role: <strong>{{ ucfirst($user->role->value) }}</strong> · {{ $user->role->description() }}</p>
        <h2>Change password</h2>
        <label>Current password <input type="password" name="current_password" autocomplete="current-password"></label>
        <label>New password <input type="password" name="password" autocomplete="new-password" minlength="10"></label>
        <label>Confirm new password <input type="password" name="password_confirmation" autocomplete="new-password"></label>
        <div class="row"><button class="btn btn-primary">Save</button></div>
    </form>
@endsection
