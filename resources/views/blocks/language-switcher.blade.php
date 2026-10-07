@php $align = in_array($props['align'] ?? 'right', ['left', 'center', 'right'], true) ? $props['align'] : 'right'; @endphp
@if(count($links) > 1)
    <nav class="atlas-lang atlas-lang--{{ ($props['style'] ?? 'inline') === 'pills' ? 'pills' : 'inline' }} atlas-align-{{ $align }}" aria-label="Language">
        @foreach($links as $link)<a href="{{ $link['url'] }}" hreflang="{{ $link['code'] }}" lang="{{ $link['code'] }}" aria-current="{{ $link['active'] ? 'true' : 'false' }}">{{ $link['name'] }}</a>@endforeach
    </nav>
@elseif($editing)
    <div class="atlas-placeholder">{{ __('atlas::ui.add_locales') }}</div>
@endif
