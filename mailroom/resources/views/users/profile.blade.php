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

    <section class="card stack narrow">
        <h2>API keys</h2>
        <p class="muted small">Keys act as you, with your role ({{ $user->role->value }}). Send them as <code>Authorization: Bearer mr_…</code> to <code>{{ url('/api/v1') }}</code>.</p>
        @if (session('new_key'))
            <label>Your new key <input readonly value="{{ session('new_key') }}" onclick="this.select()" class="mono"></label>
        @endif
        @foreach ($keys as $key)
            <div class="row-between link-row">
                <span><strong>{{ $key->name }}</strong> <code>{{ $key->prefix }}…</code> <span class="muted small">{{ $key->last_used_at ? 'used ' . $key->last_used_at->diffForHumans() : 'never used' }}</span></span>
                <form method="post" action="{{ route('profile.keys.destroy', $key) }}" onsubmit="return confirm('Revoke this key?')">@csrf @method('delete')<button class="link danger small">Revoke</button></form>
            </div>
        @endforeach
        <form method="post" action="{{ route('profile.keys.store') }}" class="row">
            @csrf
            <input name="name" placeholder="Key name, e.g. Website" required maxlength="80" aria-label="Key name" class="grow">
            <button class="btn">Create key</button>
        </form>
        <details>
            <summary>Endpoints</summary>
            <pre class="api-doc">GET    /api/v1/ping
GET    /api/v1/audiences
GET    /api/v1/audiences/{id}/contacts?status=subscribed&per_page=100&page=1
PUT    /api/v1/audiences/{id}/contacts            {email, first_name, last_name, status, status_if_new, fields{}, tags[]}
GET    /api/v1/audiences/{id}/contacts/{email}
DELETE /api/v1/audiences/{id}/contacts/{email}
POST   /api/v1/audiences/{id}/contacts/{email}/tags   {add[], remove[]}
GET    /api/v1/campaigns
GET    /api/v1/campaigns/{id}/report
POST   /api/v1/messages/send                      {to, subject, html, text, from_email, from_name, reply_to}</pre>
        </details>
    </section>
@endsection
