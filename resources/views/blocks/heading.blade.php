@php
    $size = $pick($props['size'] ?? null, ['auto', 'sm', 'md', 'lg', 'xl', '2xl'], 'auto');
    $align = $pick($props['align'] ?? null, ['left', 'center', 'right'], 'left');
@endphp
<div class="atlas-heading-wrap atlas-align-{{ $align }}">
    @if(filled($props['eyebrow'] ?? null))<div class="atlas-eyebrow">{{ $props['eyebrow'] }}</div>@endif
    <{{ $tag }} class="atlas-heading atlas-h-{{ $size }} @if($props['gradient'] ?? false) atlas-heading--gradient @endif" @if($cssColor($props['color'] ?? '') !== '' && ! ($props['gradient'] ?? false)) style="color:{{ $cssColor($props['color']) }}" @endif>{{ $props['text'] ?? '' }}</{{ $tag }}>
</div>
