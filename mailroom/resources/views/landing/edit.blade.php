@extends('layouts.app')
@section('title', $page->title)
@section('content')
    <header class="page-head">
        <div>
            <a class="muted small" href="{{ route('landing-pages.index') }}">← Landing pages</a>
            <h1>{{ $page->title }}</h1>
            <span class="badge badge-{{ $page->published ? 'sent' : 'draft' }}">{{ $page->published ? 'Published' : 'Draft' }}</span>
            <span class="muted small">{{ number_format($page->visits) }} visits · {{ number_format($page->signups) }} signups · {{ $page->conversionRate() }}% conversion</span>
        </div>
        <div class="row">
            <a class="btn btn-ghost" href="{{ route('landing-pages.preview', $page) }}" target="_blank" rel="noopener">Preview</a>
            @if ($page->published)<a class="btn" href="{{ route('landing.show', $page->slug) }}" target="_blank" rel="noopener">View live page</a>@endif
        </div>
    </header>
    <form method="post" id="campaign-form" action="{{ route('landing-pages.update', $page) }}" class="editor">
        @csrf @method('put')
        <input type="hidden" id="audience" value=""><select id="segment" hidden></select><select id="tag" hidden></select>
        <section class="card stack">
            <h2>Page settings</h2>
            <div class="grid-2">
                <label>Title <small class="muted">(browser tab and search results)</small><input name="title" value="{{ old('title', $page->title) }}" required maxlength="150"></label>
                <label>URL <span class="url-field"><span class="muted">{{ url('/p') }}/</span><input name="slug" value="{{ old('slug', $page->slug) }}" required pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="100"></span></label>
            </div>
            <label>Description <small class="muted">(search engines and social shares)</small><input name="meta_description" value="{{ old('meta_description', $page->meta_description) }}" maxlength="300"></label>
            <div class="grid-2">
                <label>Tag new signups with <input name="tag" value="{{ old('tag', $page->tag) }}" maxlength="60" placeholder="e.g. landing-launch"></label>
                <label class="check"><input type="checkbox" name="published" value="1" @checked(old('published', $page->published))> Published</label>
            </div>
        </section>
        <section class="card">
            <h2>Design</h2>
            <input type="hidden" name="design" id="design" value="{{ json_encode($page->design) }}">
            <div class="builder" id="builder" data-landing="1" data-preview-url="{{ route('builder.preview') }}" data-media-url="{{ route('media.index') }}" data-upload-url="{{ route('media.store') }}"
                 data-blocks='@json($blockTypes)' data-defaults='@json($blockDefaults)' data-social='@json($socialNetworks)'>
                <aside class="builder-palette" aria-label="Blocks"><h3>Blocks</h3>
                    @foreach ($blockTypes as $type => $label)<button type="button" class="palette-item" draggable="true" data-add="{{ $type }}">{{ $label }}</button>@endforeach
                    <h3>Layout</h3><ol class="outline" id="outline"></ol>
                </aside>
                <div class="builder-canvas">
                    <div class="device-toggle"><button type="button" data-width="100%" class="active">Desktop</button><button type="button" data-width="390px">Mobile</button></div>
                    <iframe id="builder-preview" title="Page design" sandbox="allow-same-origin"></iframe>
                </div>
                <aside class="builder-inspector" id="inspector"></aside>
            </div>
        </section>
        <div class="actions"><button class="btn btn-primary">Save page</button></div>
    </form>
    <form method="post" action="{{ route('landing-pages.destroy', $page) }}" onsubmit="return confirm('Delete this page?')">@csrf @method('delete')<button class="btn btn-ghost danger">Delete page</button></form>
@endsection
@push('scripts')
<script src="{{ asset('js/campaign-editor.js') }}"></script>
<script src="{{ asset('js/email-builder.js') }}"></script>
@endpush
