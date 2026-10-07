{{-- Raw, trusted editor code: HTML, then CSS (already scoped), then JavaScript. --}}
{!! $props['html'] ?? '' !!}
@if(filled($props['css'] ?? null))
<style>{!! $props['css'] !!}</style>
@endif
@if(filled($props['js'] ?? null) && ! $editing)
<script>
@if($props['isolate'] ?? true)
(function () {
    var el = document.getElementById({!! json_encode($domId) !!});
{!! $props['js'] !!}
}).call(window);
@else
{!! $props['js'] !!}
@endif
</script>
@endif
