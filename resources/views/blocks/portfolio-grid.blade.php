@php
    $style = $pick($props['style'] ?? null, ['overlay', 'caption', 'minimal'], 'overlay');
    $hover = $pick($props['hover'] ?? null, ['zoom', 'lift', 'none'], 'zoom');
    $ratio = $aspect($props['ratio'] ?? null, '4/3');
    $cols = min(4, max(2, $int($props['columns'] ?? null, 3)));
@endphp
<div class="atlas-portfolio atlas-pf--{{ $style }} atlas-pf-hover--{{ $hover }}" style="--cols:{{ $cols }};--gap:{{ $int($props['gap'] ?? null, 20) }}px;--ratio:{{ $ratio }}" data-atlas-portfolio data-atlas-rt>
    @if(($props['filter'] ?? true) && count($categories) > 1)
        <div class="atlas-pf__filters" role="group" aria-label="Filter">
            <button type="button" class="is-active" data-filter="*" aria-pressed="true">{{ $props['all_label'] ?? 'All' }}</button>
            @foreach($categories as $cat)<button type="button" data-filter="{{ $cat }}" aria-pressed="false">{{ $cat }}</button>@endforeach
        </div>
    @endif
    <div class="atlas-pf__grid">
        @foreach($items as $item)
            @php
                $img = $safe($item['image'] ?? '');
                $href = $safe($item['url'] ?? '');
                $zoom = $href === '' && $img !== '' && ($props['lightbox'] ?? true);
                $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($item['tags'] ?? '')))));
            @endphp
            <article class="atlas-pf__item" data-category="{{ $item['category'] ?? '' }}">
                <a class="atlas-pf__media" @if($href !== '') href="{{ $href }}" @elseif($zoom) href="{{ $img }}" data-atlas-lightbox data-caption="{{ $item['title'] ?? '' }}" @endif aria-label="{{ $item['title'] ?? '' }}">
                    @if($img !== '')<img src="{{ $img }}" alt="{{ $item['title'] ?? '' }}" loading="lazy">@else<span class="atlas-pf__ph"></span>@endif
                    @if($style === 'overlay')
                        <span class="atlas-pf__overlay"><strong>{{ $item['title'] ?? '' }}</strong>@if(filled($item['category'] ?? null))<em>{{ $item['category'] }}</em>@endif</span>
                    @endif
                </a>
                @if($style !== 'overlay')
                    <div class="atlas-pf__body">
                        @if($style === 'caption' && filled($item['category'] ?? null))<div class="atlas-pf__cat">{{ $item['category'] }}</div>@endif
                        <h3>{{ $item['title'] ?? '' }}</h3>
                        @if($style === 'caption' && filled($item['description'] ?? null))<p>{{ $item['description'] }}</p>@endif
                        @if($style === 'caption' && $tags)<ul class="atlas-tags">@foreach($tags as $t)<li>{{ $t }}</li>@endforeach</ul>@endif
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</div>
