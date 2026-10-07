@php
    $ratio = ($props['ratio'] ?? '1/1') === 'auto' ? 'auto' : (preg_match('/^\d+\/\d+$/', $props['ratio'] ?? '') ? $props['ratio'] : '1/1');
@endphp
<div class="atlas-gallery" style="--cols:{{ min(5, max(2, (int) ($props['columns'] ?? 3))) }};--gap:{{ (int) ($props['gap'] ?? 12) }}px;--ratio:{{ $ratio }}" data-atlas-rt>
    @foreach(($props['items'] ?? []) as $item)
        @php($img = $safe($item['image'] ?? ''))
        <figure>
            @if($img !== '' && ($props['lightbox'] ?? true))
                <a href="{{ $img }}" data-atlas-lightbox data-caption="{{ $item['caption'] ?: ($item['alt'] ?? '') }}"><img src="{{ $img }}" alt="{{ $item['alt'] ?? '' }}" loading="lazy"></a>
            @elseif($img !== '')
                <div class="atlas-gallery__img"><img src="{{ $img }}" alt="{{ $item['alt'] ?? '' }}" loading="lazy"></div>
            @else
                <div class="atlas-gallery__img"><div class="atlas-gallery__ph"></div></div>
            @endif
            @if(filled($item['caption'] ?? null))<figcaption>{{ $item['caption'] }}</figcaption>@endif
        </figure>
    @endforeach
</div>
