@php $align = ($props['align'] ?? 'center') === 'left' ? 'left' : 'center'; @endphp
<div class="atlas-counters atlas-align-{{ $align }}" data-atlas-reveal data-atlas-rt>
    @foreach(($props['items'] ?? []) as $item)
        <div class="atlas-counter">
            <div class="atlas-counter__num">{{ $item['prefix'] ?? '' }}<span data-atlas-count="{{ $item['value'] ?? 0 }}" data-duration="{{ $int($props['duration'] ?? null, 1800) }}">{{ $item['value'] ?? 0 }}</span>{{ $item['suffix'] ?? '' }}</div>
            <div class="atlas-counter__label">{{ $item['label'] ?? '' }}</div>
        </div>
    @endforeach
</div>
