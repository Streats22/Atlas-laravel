@php
    $items = array_values(array_filter(($props['items'] ?? []), 'is_array'));
    $one = function ($item) use ($safe) {
        $img = $safe($item['image'] ?? '');
        $href = $safe($item['url'] ?? '');
        $inner = $img !== '' ? '<img src="'.e($img).'" alt="'.e($item['name'] ?? '').'" loading="lazy">' : e($item['name'] ?? '');
        return $href !== '' ? '<a href="'.e($href).'" rel="noopener">'.$inner.'</a>' : '<span>'.$inner.'</span>';
    };
    $gray = $props['grayscale'] ?? true;
    $height = $int($props['height'] ?? null, 40);
    $repeat = max(1, (int) ceil(8 / max(1, count($items))));
@endphp
@if($props['marquee'] ?? false)
    <div class="atlas-marquee atlas-marquee--logos atlas-marquee--pause @if($gray) atlas-logos--gray @endif" style="--speed:30s;--lh:{{ $height }}px">
        <div class="atlas-marquee__track">
            @for($n = 0; $n < 2; $n++)
                <ul class="atlas-marquee__list atlas-logos" aria-hidden="{{ $n ? 'true' : 'false' }}" style="flex-wrap:nowrap;gap:0">
                    @for($r = 0; $r < $repeat; $r++)@foreach($items as $item)<li>{!! $one($item) !!}</li>@endforeach @endfor
                </ul>
            @endfor
        </div>
    </div>
@else
    <div class="atlas-logos @if($gray) atlas-logos--gray @endif" style="--lh:{{ $height }}px">
        @foreach($items as $item){!! $one($item) !!}@endforeach
    </div>
@endif
