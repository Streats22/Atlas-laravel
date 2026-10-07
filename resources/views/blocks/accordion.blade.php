<div class="atlas-accordion">
    @foreach(($props['items'] ?? []) as $i => $item)
        <details class="atlas-acc__item" @if($loop->first && ($props['open_first'] ?? true)) open @endif @if($props['exclusive'] ?? true) name="{{ $domId }}" @endif>
            <summary>{{ $item['title'] ?? '' }}</summary>
            <div class="atlas-acc__body">{!! nl2br(e($item['text'] ?? '')) !!}</div>
        </details>
    @endforeach
</div>
