@extends('layouts.app')
@section('title', $campaign->exists ? $campaign->name : 'New campaign')
@section('content')
    <header class="page-head">
        <div>
            <a class="muted small" href="{{ route('campaigns.index') }}">← Campaigns</a>
            <h1>{{ $campaign->exists ? $campaign->name : 'New campaign' }}</h1>
            @if ($campaign->exists)
                <span class="badge badge-{{ $campaign->status->value }}">{{ $campaign->status->label() }}</span>
                @if ($campaign->scheduled_at) <span class="muted small">for {{ $campaign->scheduled_at->format('M j, Y H:i') }}</span> @endif
            @endif
        </div>
        @if ($campaign->exists)
            <form method="post" action="{{ route('campaigns.destroy', $campaign) }}" onsubmit="return confirm('Delete this campaign?')">
                @csrf @method('delete') <button class="btn btn-ghost danger">Delete</button>
            </form>
        @endif
    </header>

    @unless ($campaign->exists)
        <nav class="tabs" aria-label="Start from a template">
            @foreach ($templates as $key => $label)
                <a href="{{ route('campaigns.create', ['template' => $key, 'audience' => $campaign->audience_id]) }}" @class(['active' => request('template', 'simple') === $key])>{{ $label }}</a>
            @endforeach
        </nav>
    @endunless

    <form method="post" id="campaign-form" action="{{ $campaign->exists ? route('campaigns.update', $campaign) : route('campaigns.store') }}" class="editor">
        @csrf
        @if ($campaign->exists) @method('put') @endif
        <section class="card stack">
            <h2>Setup</h2>
            <label>Campaign name <small class="muted">(internal)</small><input name="name" value="{{ old('name', $campaign->name) }}" required maxlength="150"></label>
            <div class="grid-2">
                <label>Audience
                    <select name="audience_id" id="audience" required>
                        @foreach ($audiences as $a)
                            <option value="{{ $a->id }}" @selected(old('audience_id', $campaign->audience_id) == $a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Segment
                    <select name="segment_id" id="segment"><option value="">Entire audience</option>
                        @foreach ($audiences as $a) @foreach ($a->segments as $s)
                            <option value="{{ $s->id }}" data-audience="{{ $a->id }}" @selected(old('segment_id', $campaign->segment_id) == $s->id)>{{ $s->name }}</option>
                        @endforeach @endforeach
                    </select>
                </label>
                <label>Only contacts tagged
                    <select name="tag_id" id="tag"><option value="">Any tag</option>
                        @foreach ($audiences as $a) @foreach ($a->tags as $t)
                            <option value="{{ $t->id }}" data-audience="{{ $a->id }}" @selected(old('tag_id', $campaign->tag_id) == $t->id)>{{ $t->name }}</option>
                        @endforeach @endforeach
                    </select>
                </label>
                @isset($recipientCount)
                    <div class="recipients"><strong>{{ number_format($recipientCount) }}</strong> subscribed recipients<br><small class="muted">at last save</small></div>
                @endisset
            </div>
            <div class="grid-2">
                <label>From name <input name="from_name" value="{{ old('from_name', $campaign->from_name) }}" required maxlength="120"></label>
                <label>From email <input type="email" name="from_email" value="{{ old('from_email', $campaign->from_email) }}" required></label>
            </div>
            <label>Subject line <input name="subject" id="subject" value="{{ old('subject', $campaign->subject) }}" required maxlength="190"></label>
            <label>Preview text <small class="muted">(shown after the subject in most inboxes)</small><input name="preview_text" value="{{ old('preview_text', $campaign->preview_text) }}" maxlength="190"></label>
        </section>

        <section class="card">
            <div class="row-between">
                <h2>Content</h2>
                <div class="chips" aria-label="Insert merge tag">
                    @foreach ($mergeTags as $tag)<button type="button" class="chip" data-tag="{{ $tag }}">{{ $tag }}</button>@endforeach
                </div>
            </div>
            <div class="split">
                <textarea name="html" id="html" spellcheck="false" required>{{ old('html', $campaign->html) }}</textarea>
                <div class="preview-wrap">
                    <div class="device-toggle"><button type="button" data-width="100%" class="active">Desktop</button><button type="button" data-width="390px">Mobile</button></div>
                    <iframe id="preview" title="Email preview" sandbox=""></iframe>
                </div>
            </div>
            <p class="muted small">Use <code>@{{ first_name | fallback }}</code> for a fallback value. Links are tracked automatically; an unsubscribe footer is always added.</p>
        </section>
        <div class="actions"><button class="btn btn-primary">Save</button></div>
    </form>

    @if ($campaign->exists)
        <div class="grid-3">
            <form method="post" action="{{ route('campaigns.test', $campaign) }}" class="card stack">
                @csrf
                <h2>Send a test</h2>
                <label>Email addresses <small class="muted">(comma-separated, max 5)</small><input name="emails" value="{{ old('emails', auth()->user()->email) }}"></label>
                <button class="btn">Send test</button>
            </form>
            <form method="post" action="{{ route('campaigns.schedule', $campaign) }}" class="card stack">
                @csrf
                <h2>Schedule</h2>
                <label>Send at <input type="datetime-local" name="scheduled_at" value="{{ $campaign->scheduled_at?->format('Y-m-d\TH:i') }}" required></label>
                <button class="btn">Schedule</button>
                @if ($campaign->status === \App\Enums\CampaignStatus::Scheduled)
                    <button class="btn btn-ghost" formaction="{{ route('campaigns.unschedule', $campaign) }}" formnovalidate>Unschedule</button>
                @endif
            </form>
            <form method="post" action="{{ route('campaigns.send', $campaign) }}" class="card stack" onsubmit="return confirm('Send this campaign to {{ $recipientCount ?? 0 }} recipients now?')">
                @csrf
                <h2>Send now</h2>
                <p class="muted">Save your changes first. Sending can't be undone.</p>
                <button class="btn btn-primary" @disabled(($recipientCount ?? 0) === 0)>Send to {{ number_format($recipientCount ?? 0) }}</button>
            </form>
        </div>
    @endif
@endsection

@push('scripts')
<script src="{{ asset('js/campaign-editor.js') }}"></script>
@endpush
