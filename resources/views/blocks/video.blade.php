@php $ratio = preg_match('/^\d+\/\d+$/', $props['ratio'] ?? '') ? $props['ratio'] : '16/9'; @endphp
@if($embed || $file)
    <figure class="atlas-video">
        <div class="atlas-video__frame" style="--r:{{ $ratio }}">
            @if($embed)
                <iframe src="{{ $embed }}" loading="lazy" title="{{ $props['caption'] ?: 'Video' }}" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            @else
                <video src="{{ $file }}" controls playsinline preload="metadata" @if($props['poster'] ?? null) poster="{{ $safe($props['poster']) }}" @endif @if($props['autoplay'] ?? false) autoplay muted @endif @if($props['loop'] ?? false) loop @endif></video>
            @endif
        </div>
        @if(filled($props['caption'] ?? null))<figcaption>{{ $props['caption'] }}</figcaption>@endif
    </figure>
@elseif($editing)
    <div class="atlas-placeholder">{{ __('atlas::ui.add_video') }}</div>
@endif
