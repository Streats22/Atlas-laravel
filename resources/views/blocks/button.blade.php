@php
    $color = $props['color'] ?: '#4f46e5';
    $style = ($props['style'] ?? 'solid') === 'outline'
        ? "border:2px solid {$color};color:{$color};background:transparent;"
        : "border:2px solid {$color};background:{$color};color:#fff;";
@endphp
<div class="atlas-button-wrap" style="text-align:{{ $props['align'] ?? 'left' }}">
    <a class="atlas-button" href="{{ $href }}" @if($props['new_tab'] ?? false) target="_blank" rel="noopener noreferrer" @endif style="display:inline-block;padding:.7em 1.4em;border-radius:6px;text-decoration:none;font-weight:600;{{ $style }}">{{ $props['label'] ?? '' }}</a>
</div>
