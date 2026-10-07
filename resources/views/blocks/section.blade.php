@php
    $widths = ['narrow' => '720px', 'normal' => '1100px', 'wide' => '1400px', 'full' => 'none'];
    $max = $widths[$props['max_width'] ?? 'normal'] ?? '1100px';
    $bg = $safe($props['bg_image'] ?? '');
    $tone = in_array($props['tone'] ?? 'none', ['none', 'surface', 'accent', 'inverted'], true) ? $props['tone'] : 'none';
    $style = '--s-py:'.(int) ($props['padding_y'] ?? 56).'px;';
    if (! empty($props['background'])) { $style .= 'background:'.$props['background'].';'; }
    if ((int) ($props['min_height'] ?? 0) > 0) { $style .= 'min-height:'.(int) $props['min_height'].'px;'; }
    $valign = in_array($props['valign'] ?? 'start', ['start', 'center', 'end'], true) ? $props['valign'] : 'start';
@endphp
<section class="atlas-section atlas-tone-{{ $tone }} @if($bg !== '') atlas-section--img @endif" data-valign="{{ $valign }}" style="{{ $style }}">
    @if($bg !== '')
        <div class="atlas-section__bg" style="background-image:url('{{ $bg }}')" @if($props['parallax'] ?? false) data-atlas-parallax data-atlas-rt @endif></div>
        @if((int) ($props['overlay'] ?? 0) > 0)<div class="atlas-section__overlay" style="opacity:{{ min(90, (int) $props['overlay']) / 100 }}"></div>@endif
    @endif
    <div class="atlas-section__inner" style="max-width:{{ $max }}">{!! $children !!}</div>
</section>
