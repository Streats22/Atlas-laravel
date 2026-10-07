<ol class="atlas-timeline @if(($props['style'] ?? 'line') === 'alternate') atlas-timeline--alternate @endif">
    @foreach(($props['items'] ?? []) as $item)
        <li>
            <div class="atlas-timeline__period">{{ $item['period'] ?? '' }}</div>
            <h3>{{ $item['title'] ?? '' }}</h3>
            @if(filled($item['subtitle'] ?? null))<p class="atlas-timeline__sub">{{ $item['subtitle'] }}</p>@endif
            @if(filled($item['text'] ?? null))<p>{!! nl2br(e($item['text'])) !!}</p>@endif
        </li>
    @endforeach
</ol>
