@php
    $items = $props['items'] ?? [];
    $carousel = ($props['layout'] ?? 'grid') === 'carousel';
    $card = function ($item) use ($safe) {
        $av = $safe($item['avatar'] ?? '');
        return compact('item', 'av');
    };
@endphp
@if($carousel)
<div class="atlas-carousel" data-atlas-carousel data-autoplay="6000" data-atlas-rt style="--h:auto">
    <div class="atlas-carousel__track">
        @foreach($items as $item)
            @php($av = $safe($item['avatar'] ?? ''))
            <div class="atlas-carousel__slide atlas-carousel__slide--plain" style="min-height:0">
                <figure class="atlas-quote" style="margin:1rem auto 3rem;max-width:44rem;border:0;background:none">
                    <blockquote>{{ $item['quote'] ?? '' }}</blockquote>
                    <figcaption>@if($av !== '')<img class="atlas-quote__avatar" src="{{ $av }}" alt="" loading="lazy">@else<span class="atlas-quote__avatar" aria-hidden="true">{{ mb_substr($item['name'] ?? '?', 0, 1) }}</span>@endif<span><strong>{{ $item['name'] ?? '' }}</strong><small>{{ $item['role'] ?? '' }}</small></span></figcaption>
                </figure>
            </div>
        @endforeach
    </div>
    <div class="atlas-carousel__dots" style="bottom:.4rem"></div>
</div>
@else
<div class="atlas-quotes" style="--cols:{{ min(3, max(1, (int) ($props['columns'] ?? 2))) }}">
    @foreach($items as $item)
        @php($av = $safe($item['avatar'] ?? ''))
        <figure class="atlas-quote">
            <blockquote>{{ $item['quote'] ?? '' }}</blockquote>
            <figcaption>@if($av !== '')<img class="atlas-quote__avatar" src="{{ $av }}" alt="" loading="lazy">@else<span class="atlas-quote__avatar" aria-hidden="true">{{ mb_substr($item['name'] ?? '?', 0, 1) }}</span>@endif<span><strong>{{ $item['name'] ?? '' }}</strong><small>{{ $item['role'] ?? '' }}</small></span></figcaption>
        </figure>
    @endforeach
</div>
@endif
