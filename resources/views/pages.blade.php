<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atlas · {{ __('atlas::ui.pages') }}</title>
    <link rel="stylesheet" href="{{ route('atlas.asset', 'atlas.css') }}">
    <script>try{var t=localStorage.getItem('atlas-ui-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
</head>
<body class="atlas-admin">
<main class="atlas-admin__main">
    <header class="atlas-admin__head">
        <h1><span class="atlas-logo">▲</span> Atlas</h1>
        <form method="post" action="{{ route('atlas.pages.store') }}" class="atlas-admin__new">
            @csrf
            <input name="title" placeholder="{{ __('atlas::ui.new_page') }}" required maxlength="255">
            <button class="atlas-btn atlas-btn--primary">{{ __('atlas::ui.create_page') }}</button>
        </form>
    </header>
    @error('title')<p class="atlas-error-text">{{ $message }}</p>@enderror

    <table class="atlas-table">
        <thead><tr><th>{{ __('atlas::ui.title') }}</th><th>{{ __('atlas::ui.url') }}</th><th>{{ __('atlas::ui.status') }}</th><th>{{ __('atlas::ui.updated') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($pages as $page)
            <tr>
                <td><a href="{{ route('atlas.pages.edit', $page) }}"><strong>{{ $page->title }}</strong></a></td>
                <td><code>/{{ $page->slug }}</code></td>
                <td><span class="atlas-pill atlas-pill--{{ $page->status->value }}">{{ $page->status->label() }}</span></td>
                <td>{{ $page->updated_at->diffForHumans() }}</td>
                <td class="atlas-actions">
                    <a class="atlas-btn" href="{{ route('atlas.pages.edit', $page) }}">{{ __('atlas::ui.edit') }}</a>
                    <a class="atlas-btn" href="{{ route('atlas.pages.preview', $page) }}" target="_blank">{{ __('atlas::ui.preview') }}</a>
                    <form method="post" action="{{ route('atlas.pages.destroy', $page) }}" onsubmit="return confirm(@js(__('atlas::ui.delete_confirm')))">
                        @csrf @method('DELETE')
                        <button class="atlas-btn atlas-btn--danger">{{ __('atlas::ui.delete') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="atlas-empty-row">{{ __('atlas::ui.no_pages') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</main>
</body>
</html>
