<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atlas · {{ __('atlas::ui.pages') }}</title>
    <link rel="stylesheet" href="{{ \Atlas\Facades\Atlas::assetUrl('atlas.css') }}">
    <script>try{var t=localStorage.getItem('atlas-ui-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
</head>
<body class="atlas-admin">
<main class="atlas-admin__main">
    <header class="atlas-admin__head">
        <h1><span class="atlas-logo">▲</span> Atlas</h1>
        <span class="atlas-admin__count">{{ $pages->total() }} {{ \Illuminate\Support\Str::lower(__('atlas::ui.pages')) }}</span>
    </header>

    <form method="post" action="{{ route('atlas.pages.store') }}" class="atlas-admin__new">
        @csrf
        <input name="title" placeholder="{{ __('atlas::ui.new_page') }}" required maxlength="255" aria-label="{{ __('atlas::ui.new_page') }}">
        @if(count($templates) > 1)
            <select name="template" title="{{ __('atlas::ui.template') }}" aria-label="{{ __('atlas::ui.template') }}">
                @foreach($templates as $key => $template)<option value="{{ $key }}" title="{{ $template->description() }}">{{ $template->label() }}</option>@endforeach
            </select>
        @endif
        <button class="atlas-btn atlas-btn--primary">＋ {{ __('atlas::ui.create_page') }}</button>
    </form>
    @error('title')<p class="atlas-error-text">{{ $message }}</p>@enderror

    <form method="get" class="atlas-admin__search">
        <input type="search" name="q" value="{{ $term }}" placeholder="{{ __('atlas::ui.search_pages') }}" aria-label="{{ __('atlas::ui.search_pages') }}">
    </form>

    <ul class="atlas-pagelist">
    @forelse($pages as $page)
        <li class="atlas-pagecard">
            <a class="atlas-pagecard__avatar" href="{{ route('atlas.pages.edit', $page) }}" aria-hidden="true" tabindex="-1" style="--h: {{ crc32($page->slug) % 360 }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($page->title, 0, 1)) }}</a>
            <div class="atlas-pagecard__main">
                <a class="atlas-pagecard__title" href="{{ route('atlas.pages.edit', $page) }}">{{ $page->title }}</a>
                <div class="atlas-pagecard__meta">
                    <code>/{{ $page->slug }}</code>
                    <span class="atlas-pill atlas-pill--{{ $page->status->value }}">{{ $page->status->label() }}</span>
                    <span class="atlas-pagecard__time">{{ $page->updated_at->diffForHumans() }}</span>
                </div>
            </div>
            <div class="atlas-actions">
                <a class="atlas-btn atlas-btn--primary" href="{{ route('atlas.pages.edit', $page) }}">{{ __('atlas::ui.edit') }}</a>
                <a class="atlas-btn" href="{{ route('atlas.pages.preview', $page) }}" target="_blank">{{ __('atlas::ui.preview') }}</a>
                <form method="post" action="{{ route('atlas.pages.duplicate', $page) }}">
                    @csrf
                    <button class="atlas-btn">{{ __('atlas::ui.duplicate') }}</button>
                </form>
                <form method="post" action="{{ route('atlas.pages.destroy', $page) }}" onsubmit="return confirm(@js(__('atlas::ui.delete_confirm')))">
                    @csrf @method('DELETE')
                    <button class="atlas-btn atlas-btn--danger">{{ __('atlas::ui.delete') }}</button>
                </form>
            </div>
        </li>
    @empty
        <li class="atlas-empty-row">{{ __('atlas::ui.no_pages') }}</li>
    @endforelse
    </ul>
    @if($pages->hasPages())
        <nav class="atlas-pager">
            @if($pages->onFirstPage())<span class="atlas-btn" disabled>←</span>@else<a class="atlas-btn" href="{{ $pages->previousPageUrl() }}">←</a>@endif
            <span>{{ $pages->currentPage() }} / {{ $pages->lastPage() }}</span>
            @if($pages->hasMorePages())<a class="atlas-btn" href="{{ $pages->nextPageUrl() }}">→</a>@else<span class="atlas-btn" disabled>→</span>@endif
        </nav>
    @endif
</main>
</body>
</html>
