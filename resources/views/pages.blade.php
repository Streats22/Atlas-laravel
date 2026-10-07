<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atlas · Pages</title>
    <link rel="stylesheet" href="{{ route('atlas.asset', 'atlas.css') }}">
</head>
<body class="atlas-admin">
<main class="atlas-admin__main">
    <header class="atlas-admin__head">
        <h1><span class="atlas-logo">▲</span> Atlas</h1>
        <form method="post" action="{{ route('atlas.pages.store') }}" class="atlas-admin__new">
            @csrf
            <input name="title" placeholder="New page title…" required maxlength="255">
            <button class="atlas-btn atlas-btn--primary">Create page</button>
        </form>
    </header>
    @error('title')<p class="atlas-error-text">{{ $message }}</p>@enderror

    <table class="atlas-table">
        <thead><tr><th>Title</th><th>URL</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        @forelse($pages as $page)
            <tr>
                <td><a href="{{ route('atlas.pages.edit', $page) }}"><strong>{{ $page->title }}</strong></a></td>
                <td><code>/{{ $page->slug }}</code></td>
                <td><span class="atlas-pill atlas-pill--{{ $page->status }}">{{ $page->status }}</span></td>
                <td>{{ $page->updated_at->diffForHumans() }}</td>
                <td class="atlas-actions">
                    <a class="atlas-btn" href="{{ route('atlas.pages.edit', $page) }}">Edit</a>
                    <a class="atlas-btn" href="{{ route('atlas.pages.preview', $page) }}" target="_blank">Preview</a>
                    <form method="post" action="{{ route('atlas.pages.destroy', $page) }}" onsubmit="return confirm('Delete this page?')">
                        @csrf @method('DELETE')
                        <button class="atlas-btn atlas-btn--danger">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="atlas-empty-row">No pages yet. Create your first one above.</td></tr>
        @endforelse
        </tbody>
    </table>
</main>
</body>
</html>
