@php $img = $safe($props['image'] ?? ''); $href = $safe($props['url'] ?? ''); @endphp
<div class="atlas-showcase @if(($props['image_side'] ?? 'left') === 'right') atlas-showcase--right @endif">
    <div class="atlas-showcase__media">@if($img !== '')<img src="{{ $img }}" alt="{{ $props['title'] ?? '' }}" loading="lazy">@else<div class="atlas-showcase__ph"></div>@endif</div>
    <div class="atlas-showcase__body">
        <h3>{{ $props['title'] ?? '' }}</h3>
        @if(filled($props['summary'] ?? null))<p class="atlas-showcase__summary">{{ $props['summary'] }}</p>@endif
        <div class="atlas-text atlas-text--md">{!! $html !!}</div>
        @if(! empty($props['meta']))
            <dl>@foreach($props['meta'] as $m)<dt>{{ $m['label'] ?? '' }}</dt><dd>{{ $m['value'] ?? '' }}</dd>@endforeach</dl>
        @endif
        @if($tags)<ul class="atlas-tags" style="margin-bottom:1.2rem">@foreach($tags as $t)<li>{{ $t }}</li>@endforeach</ul>@endif
        @if($href !== '')<a class="atlas-btn atlas-btn--solid" href="{{ $href }}">{{ $props['url_label'] ?: 'View project' }} <span aria-hidden="true">→</span></a>@endif
    </div>
</div>
