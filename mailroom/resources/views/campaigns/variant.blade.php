@extends('layouts.app')
@section('title', 'Variant ' . $variant->label)
@section('content')
    <header class="page-head"><div>
        <a class="muted small" href="{{ route('campaigns.edit', $campaign) }}#ab-test">← {{ $campaign->name }}</a>
        <h1>Content variant {{ $variant->label }}</h1>
    </div></header>
    <form method="post" id="campaign-form" action="{{ route('campaigns.variants.update', [$campaign, $variant]) }}" class="editor">
        @csrf @method('put')
        <input type="hidden" id="audience" value=""><select id="segment" hidden></select><select id="tag" hidden></select>
        <section class="card">
            <div class="row-between"><h2>Content</h2>
                <div class="chips">@foreach ($mergeTags as $tag)<button type="button" class="chip" data-tag="{{ $tag }}">{{ $tag }}</button>@endforeach</div>
            </div>
            @if ($variant->design)
                <input type="hidden" name="design" id="design" value="{{ json_encode($variant->design) }}">
                <div class="builder" id="builder" data-preview-url="{{ route('builder.preview') }}" data-media-url="{{ route('media.index') }}" data-upload-url="{{ route('media.store') }}"
                     data-blocks='@json($blockTypes)' data-defaults='@json($blockDefaults)' data-social='@json($socialNetworks)'>
                    <aside class="builder-palette" aria-label="Blocks"><h3>Blocks</h3>
                        @foreach ($blockTypes as $type => $label)<button type="button" class="palette-item" draggable="true" data-add="{{ $type }}">{{ $label }}</button>@endforeach
                        <h3>Layout</h3><ol class="outline" id="outline"></ol>
                    </aside>
                    <div class="builder-canvas">
                        <div class="device-toggle"><button type="button" data-width="100%" class="active">Desktop</button><button type="button" data-width="390px">Mobile</button></div>
                        <iframe id="builder-preview" title="Email design" sandbox="allow-same-origin"></iframe>
                    </div>
                    <aside class="builder-inspector" id="inspector"></aside>
                </div>
            @else
                <div class="split">
                    <textarea name="html" id="html" spellcheck="false" required>{{ old('html', $variant->html) }}</textarea>
                    <div class="preview-wrap">
                        <div class="device-toggle"><button type="button" data-width="100%" class="active">Desktop</button><button type="button" data-width="390px">Mobile</button></div>
                        <iframe id="preview" title="Email preview" sandbox=""></iframe>
                    </div>
                </div>
            @endif
        </section>
        <div class="actions"><button class="btn btn-primary">Save variant {{ $variant->label }}</button></div>
    </form>
@endsection
@push('scripts')
<script src="{{ asset('js/campaign-editor.js') }}"></script>
<script src="{{ asset('js/email-builder.js') }}"></script>
@endpush
