@php
    $layout = preg_match('/^(\d+fr ?)+$/', trim($props['layout'] ?? '')) ? trim($props['layout']) : '1fr 1fr';
    $align = $pick($props['align'] ?? null, ['stretch', 'start', 'center', 'end'], 'stretch');
@endphp
<div class="atlas-columns @if($props['stack'] ?? true) atlas-columns--stack @endif @if($props['reverse'] ?? false) atlas-columns--reverse @endif" style="grid-template-columns:{{ $layout }};gap:{{ $int($props['gap'] ?? null, 0) }}px;align-items:{{ $align }};">{!! $children !!}</div>
