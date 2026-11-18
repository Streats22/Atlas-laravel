@extends('layouts.app')
@section('title', $audience->exists ? 'Audience settings' : 'New audience')
@section('content')
    <header class="page-head"><h1>{{ $audience->exists ? $audience->name . ' settings' : 'New audience' }}</h1></header>
    <form method="post" action="{{ $audience->exists ? route('audiences.update', $audience) : route('audiences.store') }}" class="card stack narrow">
        @csrf
        @if ($audience->exists) @method('put') @endif
        <label>Audience name <input name="name" value="{{ old('name', $audience->name) }}" required maxlength="120"></label>
        <label>Default from name <input name="from_name" value="{{ old('from_name', $audience->from_name) }}" required maxlength="120"></label>
        <label>Default from email <input type="email" name="from_email" value="{{ old('from_email', $audience->from_email) }}" required></label>
        <label class="check"><input type="checkbox" name="double_opt_in" value="1" @checked(old('double_opt_in', $audience->double_opt_in))> Require double opt-in for signup forms</label>
        <div class="row"><button class="btn btn-primary">Save</button></div>
    </form>
    @if ($audience->exists)
        <form method="post" action="{{ route('audiences.destroy', $audience) }}" class="card narrow" onsubmit="return confirm('Delete this audience and all its contacts? This cannot be undone.')">
            @csrf @method('delete')
            <h2>Danger zone</h2>
            <p class="muted">Deletes every contact, segment, tag and campaign in this audience.</p>
            <button class="btn danger">Delete audience</button>
        </form>
    @endif
@endsection
