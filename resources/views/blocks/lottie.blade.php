@php $src = $safe($props['src'] ?? ''); $align = in_array($props['align'] ?? 'center', ['left', 'center', 'right'], true) ? $props['align'] : 'center'; @endphp
@if($src !== '')
    <div class="atlas-lottie atlas-align-{{ $align }}">
        <div data-atlas-lottie="{{ $src }}" data-autoplay="{{ ($props['autoplay'] ?? true) ? 1 : 0 }}" data-loop="{{ ($props['loop'] ?? true) ? 1 : 0 }}" data-atlas-rt style="width:{{ $props['width'] ?? '320px' }};max-width:100%"></div>
    </div>
@elseif($editing)
    <div class="atlas-placeholder">{{ __('atlas::ui.add_lottie') }}</div>
@endif
