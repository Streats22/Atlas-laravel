@php
    $src = $safe($props['src'] ?? '');
    $href = $safe($props['link'] ?? '');
    $ratio = preg_match('/^\d+\/\d+$/', $props['ratio'] ?? '') ? $props['ratio'] : 'auto';
    $fit = ($props['fit'] ?? 'cover') === 'contain' ? 'contain' : 'cover';
    $lightbox = ($props['lightbox'] ?? false) && $href === '';
@endphp
@if($src !== '')
    <figure class="atlas-image @if($props['shadow'] ?? false) atlas-image--shadow @endif" style="width:{{ $props['width'] ?? '100%' }};max-width:100%">
        @if($href !== '')<a href="{{ $href }}">@elseif($lightbox)<a href="{{ $src }}" data-atlas-lightbox data-caption="{{ $props['caption'] ?: ($props['alt'] ?? '') }}" data-atlas-rt>@endif
        <img class="atlas-image__img" src="{{ $src }}" alt="{{ $props['alt'] ?? '' }}" loading="lazy" style="aspect-ratio:{{ $ratio }};object-fit:{{ $fit }};border-radius:{{ (int) ($props['radius'] ?? 0) }}px">
        @if($href !== '' || $lightbox)</a>@endif
        @if(filled($props['caption'] ?? null))<figcaption>{{ $props['caption'] }}</figcaption>@endif
    </figure>
@elseif($editing)
    <div class="atlas-placeholder">{{ __('atlas::ui.choose_image') }}</div>
@endif
