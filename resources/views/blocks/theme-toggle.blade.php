@php $align = in_array($props['align'] ?? 'right', ['left', 'center', 'right'], true) ? $props['align'] : 'right'; @endphp
<div class="atlas-btn-wrap atlas-align-{{ $align }}">
    <button type="button" class="atlas-btn atlas-btn--ghost atlas-btn--sm" data-atlas-theme-toggle data-atlas-rt aria-label="{{ __('atlas::ui.toggle_theme') }}"><span class="atlas-ico-sun" aria-hidden="true">☀</span><span class="atlas-ico-moon" aria-hidden="true">☾</span>@if(($props['style'] ?? 'label') === 'label') <span>{{ __('atlas::ui.toggle_theme') }}</span>@endif</button>
</div>
