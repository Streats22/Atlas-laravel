<header class="page-head">
    <div>
        <a class="muted small" href="{{ route('audiences.index') }}">← Audiences</a>
        <h1>{{ $audience->name }}</h1>
    </div>
    <div class="row">
        <a class="btn btn-ghost" href="{{ route('audiences.edit', $audience) }}">Settings</a>
        <a class="btn btn-primary" href="{{ route('campaigns.create', ['audience' => $audience->id]) }}">Send campaign</a>
    </div>
</header>
<nav class="tabs">
    <a href="{{ route('audiences.show', $audience) }}" @class(['active' => request()->routeIs('audiences.show')])>Contacts</a>
    <a href="{{ route('audiences.segments.index', $audience) }}" @class(['active' => request()->routeIs('audiences.segments.*')])>Segments</a>
    <a href="{{ route('audiences.import', $audience) }}" @class(['active' => request()->routeIs('audiences.import')])>Import</a>
    <a href="{{ route('audiences.form', $audience) }}" @class(['active' => request()->routeIs('audiences.form')])>Signup form</a>
</nav>
