@php
    $color = $props['color'] ?? '';
    $isHex = is_string($color) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $color);
    $style = $isHex ? '--btn:'.$color.';--btn-fg:'.\Atlas\Support\Theme::contrast($color).';' : '';
    $variant = in_array($props['style'] ?? 'solid', ['solid', 'outline', 'ghost'], true) ? $props['style'] : 'solid';
    $size = in_array($props['size'] ?? 'md', ['sm', 'md', 'lg'], true) ? $props['size'] : 'md';
    $align = in_array($props['align'] ?? 'left', ['left', 'center', 'right'], true) ? $props['align'] : 'left';
    $href = $safe($props['url'] ?? '#') ?: '#';
@endphp
<div class="atlas-btn-wrap atlas-align-{{ $align }}">
    <a class="atlas-btn atlas-btn--{{ $variant }} atlas-btn--{{ $size }}" href="{{ $href }}" @if($props['new_tab'] ?? false) target="_blank" rel="noopener noreferrer" @endif @if($style) style="{{ $style }}" @endif>{{ $props['label'] ?? '' }}@if(filled($props['icon'] ?? null)) <span aria-hidden="true">{{ $props['icon'] }}</span>@endif</a>
</div>
