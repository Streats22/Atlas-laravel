@php $align = in_array($props['align'] ?? 'left', ['left', 'center', 'right'], true) ? $props['align'] : 'left'; @endphp
<nav class="atlas-social atlas-align-{{ $align }}" aria-label="Social">
    @foreach(($props['items'] ?? []) as $item)
        <a href="{{ $safe($item['url'] ?? '#') ?: '#' }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $item['label'] ?? '' }}" title="{{ $item['label'] ?? '' }}">{{ $item['icon'] ?: mb_substr($item['label'] ?? '', 0, 2) }}</a>
    @endforeach
</nav>
