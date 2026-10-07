@php
    $layout = preg_match('/^(\d+fr ?)+$/', trim($props['layout'] ?? '')) ? trim($props['layout']) : '1fr 1fr';
    $align = in_array($props['align'] ?? 'stretch', ['stretch', 'start', 'center', 'end'], true) ? $props['align'] : 'stretch';
@endphp
<div class="atlas-columns @if($props['stack'] ?? true) atlas-columns--stack @endif @if($props['reverse'] ?? false) atlas-columns--reverse @endif" style="grid-template-columns:{{ $layout }};gap:{{ (int) ($props['gap'] ?? 0) }}px;align-items:{{ $align }};">{!! $children !!}</div>
