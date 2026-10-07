@php $href = $safe($props['link'] ?? ''); $align = ($props['align'] ?? 'left') === 'center' ? 'center' : 'left'; @endphp
<div class="atlas-feature @if($props['card'] ?? true) atlas-feature--card @endif atlas-align-{{ $align }}">
    <div class="atlas-feature__icon" aria-hidden="true">{{ $props['icon'] ?? '' }}</div>
    <h3>@if($href !== '')<a href="{{ $href }}">{{ $props['title'] ?? '' }}</a>@else{{ $props['title'] ?? '' }}@endif</h3>
    <p>{!! nl2br(e($props['text'] ?? '')) !!}</p>
</div>
