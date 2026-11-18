@php($ab = $campaign->ab_test ?? [])
@php($variants = $campaign->variants->keyBy('label'))
<form method="post" action="{{ route('campaigns.ab.update', $campaign) }}" class="card stack" id="ab-test">
    @csrf @method('put')
    <div class="row-between">
        <h2>A/B test</h2>
        <label class="check"><input type="checkbox" name="enabled" value="1" id="ab-enabled" @checked(! empty($ab['enabled']))> Test variations of this campaign</label>
    </div>
    <div class="ab-fields stack" @if (empty($ab['enabled'])) hidden @endif>
        <div class="grid-2">
            <label>What do you want to test?
                <select name="variable" id="ab-variable">
                    @foreach (\App\Services\AbTest::VARIABLES as $k => $l)<option value="{{ $k }}" @selected(($ab['variable'] ?? 'subject') === $k)>{{ $l }}</option>@endforeach
                </select>
            </label>
            <label>Number of variants
                <select name="variants" id="ab-count"><option value="2" @selected(($ab['variants'] ?? 2) == 2)>2 (A, B)</option><option value="3" @selected(($ab['variants'] ?? 2) == 3)>3 (A, B, C)</option></select>
            </label>
        </div>

        <div class="ab-variable" data-variable="subject">
            <p class="muted small">Variant A uses the subject above: “{{ $campaign->subject }}”.</p>
            @foreach (['B', 'C'] as $l)<label class="ab-variant" data-label="{{ $l }}">Subject {{ $l }} <input name="subject[{{ $l }}]" value="{{ old("subject.$l", $variants[$l]->subject ?? '') }}" maxlength="190"></label>@endforeach
        </div>
        <div class="ab-variable" data-variable="from_name">
            <p class="muted small">Variant A uses “{{ $campaign->from_name }}”.</p>
            @foreach (['B', 'C'] as $l)<label class="ab-variant" data-label="{{ $l }}">From name {{ $l }} <input name="from_name[{{ $l }}]" value="{{ old("from_name.$l", $variants[$l]->from_name ?? '') }}" maxlength="120"></label>@endforeach
        </div>
        <div class="ab-variable" data-variable="content">
            <p class="muted small">Variant A is the content above. Save the test, then design the other variants.</p>
            <div class="row">
                @foreach (['B', 'C'] as $l)
                    @if (isset($variants[$l]) && ($ab['variable'] ?? '') === 'content')
                        <a class="btn ab-variant" data-label="{{ $l }}" href="{{ route('campaigns.variants.edit', [$campaign, $variants[$l]]) }}">Edit content {{ $l }}</a>
                    @endif
                @endforeach
            </div>
        </div>
        <div class="ab-variable" data-variable="send_time">
            <p class="muted small">Each test group is sent this many hours after you press send.</p>
            <div class="grid-3">
                @foreach (['A', 'B', 'C'] as $i => $l)<label class="ab-variant" data-label="{{ $l }}">Group {{ $l }}: delay (hours) <input type="number" min="0" max="72" name="send_delay[{{ $l }}]" value="{{ $variants[$l]->send_delay_hours ?? $i * 2 }}"></label>@endforeach
            </div>
        </div>

        <div class="grid-3">
            <label>Test group size <small class="muted">(% of recipients)</small>
                <input type="number" name="test_percent" min="10" max="100" value="{{ $ab['test_percent'] ?? 20 }}">
            </label>
            <label>Choose the winner by
                <select name="winner_by">
                    @foreach (['opens' => 'Open rate', 'clicks' => 'Click rate', 'manual' => 'Manually'] as $k => $l)<option value="{{ $k }}" @selected(($ab['winner_by'] ?? 'opens') === $k)>{{ $l }}</option>@endforeach
                </select>
            </label>
            <label>Wait before choosing <small class="muted">(hours)</small>
                <input type="number" name="wait_hours" min="1" max="168" value="{{ $ab['wait_hours'] ?? 4 }}">
            </label>
        </div>
        <p class="muted small">The test group is split evenly over the variants. After the wait time the winner goes to the remaining recipients. Use 100% to send every variant to an equal share of everyone.</p>
    </div>
    <div class="row"><button class="btn">Save A/B test</button></div>
</form>
@push('scripts')
<script>
(() => {
    const enabled = document.getElementById('ab-enabled');
    const variable = document.getElementById('ab-variable');
    const count = document.getElementById('ab-count');
    const sync = () => {
        document.querySelector('.ab-fields').hidden = !enabled.checked;
        document.querySelectorAll('.ab-variable').forEach((el) => { el.hidden = el.dataset.variable !== variable.value; });
        document.querySelectorAll('.ab-variant').forEach((el) => { el.hidden = el.dataset.label === 'C' && count.value === '2'; });
    };
    [enabled, variable, count].forEach((el) => el.addEventListener('change', sync));
    sync();
})();
</script>
@endpush
