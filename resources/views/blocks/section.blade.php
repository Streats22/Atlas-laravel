@php
    $max = (int) ($props['max_width'] ?? 0);
    $inner = ($props['full_width'] ?? false) || $max <= 0 ? '' : "max-width:{$max}px;margin-left:auto;margin-right:auto;";
@endphp
<section class="atlas-section" style="padding:{{ (int) ($props['padding'] ?? 0) }}px 20px;@if(!empty($props['background']))background:{{ $props['background'] }};@endif">
    <div class="atlas-section__inner" style="{{ $inner }}">{!! $children !!}</div>
</section>
