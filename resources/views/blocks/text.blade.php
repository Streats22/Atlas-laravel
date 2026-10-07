@php
    $align = $pick($props['align'] ?? null, ['left', 'center', 'right', 'justify'], 'left');
    $size = $pick($props['size'] ?? null, ['sm', 'md', 'lg', 'xl'], 'md');
@endphp
<div class="atlas-text atlas-text--{{ $size }} atlas-align-{{ $align }} @if($props['readable'] ?? true) atlas-readable @endif" @if($cssColor($props['color'] ?? '') !== '') style="color:{{ $cssColor($props['color']) }}" @endif>{!! $html !!}</div>
