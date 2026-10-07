@php
    $size = in_array($props['size'] ?? 'auto', ['auto', 'sm', 'md', 'lg', 'xl', '2xl'], true) ? $props['size'] : 'auto';
    $align = in_array($props['align'] ?? 'left', ['left', 'center', 'right'], true) ? $props['align'] : 'left';
@endphp
<div class="atlas-heading-wrap atlas-align-{{ $align }}">
    @if(filled($props['eyebrow'] ?? null))<div class="atlas-eyebrow">{{ $props['eyebrow'] }}</div>@endif
    <{{ $tag }} class="atlas-heading atlas-h-{{ $size }} @if($props['gradient'] ?? false) atlas-heading--gradient @endif" @if(!empty($props['color']) && ! ($props['gradient'] ?? false)) style="color:{{ $props['color'] }}" @endif>{{ $props['text'] ?? '' }}</{{ $tag }}>
</div>
