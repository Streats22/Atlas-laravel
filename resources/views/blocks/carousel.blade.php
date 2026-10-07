@php $slides = array_values(array_filter(($props['slides'] ?? []), 'is_array')); @endphp
<div class="atlas-carousel" data-atlas-carousel data-autoplay="{{ ($props['autoplay'] ?? true) ? (int) ($props['interval'] ?? 5000) : 0 }}" data-atlas-rt style="--h:{{ (int) ($props['height'] ?? 420) }}px" role="region" aria-roledescription="carousel">
    <div class="atlas-carousel__track">
        @foreach($slides as $slide)
            @php($img = $safe($slide['image'] ?? ''))
            <div class="atlas-carousel__slide" @if($img !== '') style="background-image:url('{{ $img }}')" @endif>
                <div class="atlas-carousel__caption">
                    @php($href = $safe($slide['url'] ?? ''))
                    @if(filled($slide['title'] ?? null))<h3>@if($href !== '')<a href="{{ $href }}">{{ $slide['title'] }}</a>@else{{ $slide['title'] }}@endif</h3>@endif
                    @if(filled($slide['text'] ?? null))<p>{{ $slide['text'] }}</p>@endif
                </div>
            </div>
        @endforeach
    </div>
    @if(($props['arrows'] ?? true) && count($slides) > 1)
        <button type="button" class="atlas-carousel__prev" data-prev aria-label="Previous">‹</button>
        <button type="button" class="atlas-carousel__next" data-next aria-label="Next">›</button>
    @endif
    @if($props['dots'] ?? true)<div class="atlas-carousel__dots"></div>@endif
</div>
