@extends('layouts.app')
@section('title', 'Review ' . $campaign->name)
@section('content')
    <header class="page-head">
        <div>
            <a class="muted small" href="{{ route('campaigns.edit', $campaign) }}">← Back to editor</a>
            <h1>Preview &amp; checklist</h1>
        </div>
        <form method="get" class="row">
            <label class="inline">Preview as
                <select name="contact" onchange="this.form.submit()">
                    @foreach ($contacts as $c)<option value="{{ $c->id }}" @selected($contact->id === $c->id)>{{ $c->email }}</option>@endforeach
                    @if ($contacts->isEmpty())<option>{{ $contact->email }} (sample)</option>@endif
                </select>
            </label>
        </form>
    </header>

    <div class="review">
        <div class="stack">
            <section class="card">
                <h2>Inbox</h2>
                <div class="inbox-row">
                    <div class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($campaign->from_name, 0, 1)) }}</div>
                    <div class="truncate">
                        <div class="row-between"><strong>{{ $campaign->from_name }}</strong><span class="muted small">{{ now()->format('H:i') }}</span></div>
                        <div class="truncate"><strong>{{ $subject }}</strong></div>
                        <div class="truncate muted">{{ $campaign->preview_text ?: 'No preview text: inboxes will show the first words of the email.' }}</div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="row-between">
                    <h2>Email</h2>
                    <div class="device-toggle" role="tablist">
                        <button type="button" class="active" data-view="desktop">Desktop</button>
                        <button type="button" data-view="mobile">Mobile</button>
                        <button type="button" data-view="dark">Dark mode</button>
                        <button type="button" data-view="text">Plain text</button>
                    </div>
                </div>
                <div class="review-frame">
                    <iframe id="review-html" title="Email preview" sandbox="" src="{{ route('campaigns.preview', [$campaign, 'contact' => $contact->id]) }}" data-light="{{ route('campaigns.preview', [$campaign, 'contact' => $contact->id]) }}" data-dark="{{ route('campaigns.preview', [$campaign, 'contact' => $contact->id, 'dark' => 1]) }}"></iframe>
                    <pre id="review-text" hidden>{{ $plainText }}</pre>
                </div>
            </section>
        </div>

        <div class="stack">
            <section class="card">
                <h2>Checklist</h2>
                <ul class="checklist">
                    @foreach ($checks as $check)
                        <li class="check-{{ $check['level'] }}">
                            <span class="check-icon" aria-label="{{ $check['level'] }}">{{ ['ok' => '✓', 'warning' => '!', 'error' => '✕'][$check['level']] }}</span>
                            <div><strong>{{ $check['title'] }}</strong><div class="muted small">{{ $check['detail'] }}</div></div>
                        </li>
                    @endforeach
                </ul>
            </section>
            <form method="post" action="{{ route('campaigns.test', $campaign) }}" class="card stack">
                @csrf
                <h2>Send a test email</h2>
                <label>Email addresses <small class="muted">(comma-separated, max 5)</small><input name="emails" value="{{ old('emails', auth()->user()->email) }}"></label>
                <button class="btn">Send test</button>
            </form>
            @if ($campaign->status->isEditable() && ! $campaign->isAutomation())
                @if (auth()->user()->mayUse('campaigns.send', 'POST'))
<form method="post" action="{{ route('campaigns.send', $campaign) }}" class="card stack" onsubmit="return confirm('Send this campaign now? This cannot be undone.')">
                    @csrf
                    <h2>Ready?</h2>
                    @php($blocked = collect($checks)->contains('level', 'error'))
                    <button class="btn btn-primary" @disabled($blocked)>Send now</button>
                    @if ($blocked)<p class="muted small">Fix the red items first.</p>@endif
                </form>
@endif
            @endif
        </div>
    </div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-view]').forEach((btn) => btn.addEventListener('click', () => {
    const frame = document.getElementById('review-html');
    const text = document.getElementById('review-text');
    const view = btn.dataset.view;
    document.querySelectorAll('[data-view]').forEach((b) => b.classList.toggle('active', b === btn));
    frame.hidden = view === 'text';
    text.hidden = view !== 'text';
    frame.style.maxWidth = view === 'mobile' ? '390px' : '100%';
    const src = view === 'dark' ? frame.dataset.dark : frame.dataset.light;
    if (frame.getAttribute('src') !== src) frame.setAttribute('src', src);
}));
</script>
@endpush
