@php
    $items = array_values(array_filter(($props['items'] ?? []), 'is_array'));
    $repeat = max(1, (int) ceil(8 / max(1, count($items))));
    $size = in_array($props['size'] ?? 'lg', ['md', 'lg', 'xl'], true) ? $props['size'] : 'lg';
    $dir = ($props['direction'] ?? 'left') === 'right' ? 'right' : 'left';
@endphp
<div class="atlas-marquee atlas-marquee--{{ $size }} atlas-marquee--{{ $dir }} @if($props['pause'] ?? true) atlas-marquee--pause @endif" style="--speed:{{ max(4, (int) ($props['speed'] ?? 24)) }}s">
    <div class="atlas-marquee__track">
        @for($n = 0; $n < 2; $n++)
            <ul class="atlas-marquee__list" aria-hidden="{{ $n ? 'true' : 'false' }}">
                @for($r = 0; $r < $repeat; $r++)
                    @foreach($items as $item)<li>{{ $item['text'] ?? '' }}</li>@if(filled($props['separator'] ?? null))<li class="sep" aria-hidden="true">{{ $props['separator'] }}</li>@endif @endforeach
                @endfor
            </ul>
        @endfor
    </div>
</div>
