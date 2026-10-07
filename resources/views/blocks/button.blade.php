@php
    $color = $cssColor($props['color'] ?? '');
    $isHex = preg_match(\Atlas\Support\Theme::HEX_COLOR, $color) === 1;
    $style = $color !== '' ? '--btn:'.$color.';--btn-fg:'.($isHex ? \Atlas\Support\Theme::contrast($color) : '#fff').';' : '';
    $variant = $pick($props['style'] ?? null, ['solid', 'outline', 'ghost'], 'solid');
    $size = $pick($props['size'] ?? null, ['sm', 'md', 'lg'], 'md');
    $align = $pick($props['align'] ?? null, ['left', 'center', 'right'], 'left');
    $href = $safe($props['url'] ?? '#') ?: '#';
@endphp
<div class="atlas-btn-wrap atlas-align-{{ $align }}">
    <a class="atlas-btn atlas-btn--{{ $variant }} atlas-btn--{{ $size }}" href="{{ $href }}" @if($props['new_tab'] ?? false) target="_blank" rel="noopener noreferrer" @endif @if($style) style="{{ $style }}" @endif>{{ $props['label'] ?? '' }}@if(filled($props['icon'] ?? null)) <span aria-hidden="true">{{ $props['icon'] }}</span>@endif</a>
</div>
