@extends('layouts.app')
@section('title', 'Users')
@section('content')
    <header class="page-head"><h1>Users</h1></header>
    <div class="grid-2">
        <section class="card">
            <h2>Team</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Name</th><th>Role</th><th></th></tr></thead>
                <tbody>@foreach ($users as $u)
                    <tr>
                        <td><strong>{{ $u->name }}</strong><div class="muted small">{{ $u->email }}</div></td>
                        <td>
                            @if ($u->is(auth()->user()) || ($u->role === \App\Enums\Role::Owner && auth()->user()->role !== \App\Enums\Role::Owner))
                                {{ ucfirst($u->role->value) }}
                            @else
                                <form method="post" action="{{ route('users.update', $u) }}">@csrf @method('put')
                                    <select name="role" onchange="this.form.submit()" aria-label="Role for {{ $u->name }}">
                                        @foreach ($roles as $r)<option value="{{ $r->value }}" @selected($u->role === $r)>{{ ucfirst($r->value) }}</option>@endforeach
                                    </select>
                                </form>
                            @endif
                        </td>
                        <td class="right">
                            @unless ($u->is(auth()->user()) || ($u->role === \App\Enums\Role::Owner && auth()->user()->role !== \App\Enums\Role::Owner))
                                <form method="post" action="{{ route('users.invite', $u) }}" class="inline-form">@csrf<button class="link small">Resend invite</button></form>
                                <form method="post" action="{{ route('users.destroy', $u) }}" class="inline-form" onsubmit="return confirm('Remove {{ $u->name }}?')">@csrf @method('delete')<button class="link small danger">Remove</button></form>
                            @endunless
                        </td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        </section>
        <div class="stack">
            <form method="post" action="{{ route('users.store') }}" class="card stack">
                @csrf
                <h2>Invite a user</h2>
                <label>Name <input name="name" value="{{ old('name') }}" required maxlength="120"></label>
                <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
                <label>Role
                    <select name="role">@foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role', 'author') === $r->value)>{{ ucfirst($r->value) }}</option>@endforeach</select>
                </label>
                <button class="btn btn-primary">Send invitation</button>
            </form>
            <section class="card">
                <h2>Roles</h2>
                <dl class="meta">@foreach (\App\Enums\Role::cases() as $r)<dt>{{ ucfirst($r->value) }}</dt><dd>{{ $r->description() }}</dd>@endforeach</dl>
            </section>
        </div>
    </div>
@endsection
