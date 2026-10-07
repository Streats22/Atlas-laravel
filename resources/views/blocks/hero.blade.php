@php
    $bg = $cssUrl($props['bg_image'] ?? '');
    $align = ($props['align'] ?? 'center') === 'left' ? 'left' : 'center';
    $primary = $safe($props['primary_url'] ?? '#') ?: '#';
    $secondary = $safe($props['secondary_url'] ?? '#') ?: '#';
@endphp
<section class="atlas-hero atlas-align-{{ $align }} @if($bg !== '') atlas-hero--img @elseif($props['gradient'] ?? false) atlas-hero--gradient @endif" style="min-height:{{ $int($props['min_height'] ?? null, 460) }}px">
    @if($bg !== '')
        <div class="atlas-hero__bg" style="background-image:url('{{ $bg }}')"></div>
        <div class="atlas-hero__overlay" style="opacity:{{ min(90, $int($props['overlay'] ?? null, 45)) / 100 }}"></div>
    @endif
    <div class="atlas-hero__inner">
        @if(filled($props['eyebrow'] ?? null))<div class="atlas-eyebrow">{{ $props['eyebrow'] }}</div>@endif
        <h1 class="atlas-hero__title">{{ $props['title'] ?? '' }}</h1>
        @if(filled($props['text'] ?? null))<p class="atlas-hero__text">{!! nl2br(e($props['text'])) !!}</p>@endif
        <div class="atlas-hero__actions">
            @if(filled($props['primary_label'] ?? null))<a class="atlas-btn atlas-btn--solid atlas-btn--lg" href="{{ $primary }}">{{ $props['primary_label'] }}</a>@endif
            @if(filled($props['secondary_label'] ?? null))<a class="atlas-btn atlas-btn--outline atlas-btn--lg" href="{{ $secondary }}">{{ $props['secondary_label'] }}</a>@endif
        </div>
    </div>
</section>
