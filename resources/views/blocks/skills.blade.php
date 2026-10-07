<div class="atlas-skills" data-atlas-reveal data-atlas-rt>
    @foreach(($props['items'] ?? []) as $item)
        @php($lvl = min(100, max(0, (int) ($item['level'] ?? 0))))
        <div class="atlas-skill">
            <div class="atlas-skill__head"><span>{{ $item['name'] ?? '' }}</span>@if($props['show_percent'] ?? true)<span>{{ $lvl }}%</span>@endif</div>
            <div class="atlas-skill__bar" role="progressbar" aria-valuenow="{{ $lvl }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $item['name'] ?? '' }}"><i style="--lvl:{{ $lvl }}%"></i></div>
        </div>
    @endforeach
</div>
