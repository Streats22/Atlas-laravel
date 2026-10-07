@php $style = $pick($props['style'] ?? null, ['solid', 'dashed', 'dotted', 'gradient'], 'solid'); @endphp
<hr class="atlas-divider atlas-divider--{{ $style }}" style="--t:{{ max(1, $int($props['thickness'] ?? null, 1)) }}px;--m:{{ $int($props['margin'] ?? null, 16) }}px;--w:{{ min(100, max(5, $int($props['width'] ?? null, 100))) }}%;@if(!empty($props['color']))--c:{{ $props['color'] }};@endif">
