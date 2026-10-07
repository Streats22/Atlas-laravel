@if($src !== '')
    @php($img = '<img src="'.e($src).'" alt="'.e($props['alt'] ?? '').'" loading="lazy" style="max-width:100%;height:auto;display:block;width:'.e($props['width'] ?? '100%').';border-radius:'.(int) ($props['radius'] ?? 0).'px">')
    @if($href !== '')<a href="{{ $href }}">{!! $img !!}</a>@else{!! $img !!}@endif
@elseif($editing)
    <div class="atlas-placeholder">Choose an image in the inspector &rarr;</div>
@endif
