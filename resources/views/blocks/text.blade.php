@php
    $align = in_array($props['align'] ?? 'left', ['left', 'center', 'right', 'justify'], true) ? $props['align'] : 'left';
    $size = in_array($props['size'] ?? 'md', ['sm', 'md', 'lg', 'xl'], true) ? $props['size'] : 'md';
@endphp
<div class="atlas-text atlas-text--{{ $size }} atlas-align-{{ $align }} @if($props['readable'] ?? true) atlas-readable @endif" @if(!empty($props['color'])) style="color:{{ $props['color'] }}" @endif>{!! $html !!}</div>
