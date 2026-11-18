@extends('layouts.app')
@section('title', $campaign->exists ? $campaign->name : 'New campaign')
@section('content')
    <header class="page-head">
        <div>
            @if ($campaign->isAutomation())
                <a class="muted small" href="{{ route('automations.show', $campaign->automationStep->automation_id) }}">← Back to automation</a>
            @else
                <a class="muted small" href="{{ route('campaigns.index') }}">← Campaigns</a>
            @endif
            <h1>{{ $campaign->exists ? $campaign->name : 'New campaign' }}</h1>
            @if ($campaign->exists)
                <span class="badge badge-{{ $campaign->status->value }}">{{ $campaign->status->label() }}</span>
                @if ($campaign->scheduled_at) <span class="muted small">for {{ $campaign->scheduled_at->format('M j, Y H:i') }}</span> @endif
            @endif
        </div>
        @if ($campaign->exists && ! $campaign->isAutomation())
            <form method="post" action="{{ route('campaigns.destroy', $campaign) }}" onsubmit="return confirm('Delete this campaign?')">
                @csrf @method('delete') <button class="btn btn-ghost danger">Delete</button>
            </form>
        @endif
    </header>

    @unless ($campaign->exists)
        <nav class="tabs" aria-label="Start from a template">
            @foreach (['builder' => 'Drag & drop'] + $templates as $key => $label)
                <a href="{{ route('campaigns.create', ['template' => $key, 'audience' => $campaign->audience_id]) }}" @class(['active' => request('template', 'builder') === $key])>{{ $label }}</a>
            @endforeach
            @foreach ($savedTemplates as $t)
                <a href="{{ route('campaigns.create', ['template' => 'saved:' . $t->id, 'audience' => $campaign->audience_id]) }}" @class(['active' => request('template') === 'saved:' . $t->id])>★ {{ $t->name }}</a>
            @endforeach
        </nav>
    @endunless

    <form method="post" id="campaign-form" action="{{ $campaign->exists ? route('campaigns.update', $campaign) : route('campaigns.store') }}" class="editor">
        @csrf
        @if ($campaign->exists) @method('put') @else <input type="hidden" name="kind" value="{{ $campaign->kind }}"> @endif
        <section class="card stack">
            <h2>Setup @if ($campaign->kind === 'rss')<span class="badge">RSS</span>@endif</h2>
            <label>Campaign name <small class="muted">(internal)</small><input name="name" value="{{ old('name', $campaign->name) }}" required maxlength="150"></label>
            @if ($campaign->resendOf)
                <p class="flash">Resend of “{{ $campaign->resendOf->name }}”: only contacts who received it and didn't open it get this email.</p>
            @endif
            @if ($campaign->isAutomation())
                <input type="hidden" name="audience_id" id="audience" value="{{ $campaign->audience_id }}">
                <select id="segment" hidden></select><select id="tag" hidden></select>
                <p class="muted small">This email is part of an automation and is sent to contacts when they reach its step.</p>
            @else
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
            @endif
            <div class="grid-2">
                <label>From name <input name="from_name" value="{{ old('from_name', $campaign->from_name) }}" required maxlength="120"></label>
                <label>From email <input type="email" name="from_email" value="{{ old('from_email', $campaign->from_email) }}" required></label>
            </div>
            <label>Subject line <input name="subject" id="subject" value="{{ old('subject', $campaign->subject) }}" required maxlength="190"></label>
            @unless ($campaign->isAutomation())
                <label class="check"><input type="checkbox" name="optimize_send_time" value="1" @checked(old('optimize_send_time', $campaign->optimize_send_time))> Send-time optimisation: deliver to each contact at the hour they usually open (within 24 hours)</label>
            @endunless
            <label>Preview text <small class="muted">(shown after the subject in most inboxes)</small><input name="preview_text" value="{{ old('preview_text', $campaign->preview_text) }}" maxlength="190"></label>
        </section>

        @php($useBuilder = old('design', $campaign->design) !== null)
        <section class="card" id="content-card">
            <div class="row-between">
                <h2>Content</h2>
                <div class="row">
                    <div class="chips" aria-label="Insert merge tag">
                        @foreach ($mergeTags as $tag)<button type="button" class="chip" data-tag="{{ $tag }}">{{ $tag }}</button>@endforeach
                    </div>
                    @if ($useBuilder)
                        <button type="button" class="btn btn-ghost small" id="to-code">Switch to code editor</button>
                    @else
                        <button type="button" class="btn btn-ghost small" id="to-builder" data-starter='@json(app(\App\Builder\EmailDesign::class)->starter())'>Use drag &amp; drop builder</button>
                    @endif
                </div>
            </div>

            @if ($useBuilder)
                @php($designValue = old('design', json_encode($campaign->design)))
                <input type="hidden" name="design" id="design" value="{{ is_string($designValue) ? $designValue : json_encode($designValue) }}">
                <div class="builder" id="builder"
                     data-preview-url="{{ route('builder.preview') }}"
                     data-media-url="{{ route('media.index') }}"
                     data-upload-url="{{ route('media.store') }}"
                     data-blocks='@json($blockTypes)'
                     data-defaults='@json($blockDefaults)'
                     data-social='@json($socialNetworks)'>
                    <aside class="builder-palette" aria-label="Blocks">
                        <h3>Blocks</h3>
                        @foreach ($blockTypes as $type => $label)
                            <button type="button" class="palette-item" draggable="true" data-add="{{ $type }}">{{ $label }}</button>
                        @endforeach
                        <h3>Layout</h3>
                        <ol class="outline" id="outline" aria-label="Email structure"></ol>
                    </aside>
                    <div class="builder-canvas">
                        <div class="device-toggle"><button type="button" data-width="100%" class="active">Desktop</button><button type="button" data-width="390px">Mobile</button></div>
                        <iframe id="builder-preview" title="Email design" sandbox="allow-same-origin"></iframe>
                    </div>
                    <aside class="builder-inspector" id="inspector" aria-label="Block settings"></aside>
                </div>
            @else
                <div class="split">
                    <textarea name="html" id="html" spellcheck="false" required>{{ old('html', $campaign->html) }}</textarea>
                    <div class="preview-wrap">
                        <div class="device-toggle"><button type="button" data-width="100%" class="active">Desktop</button><button type="button" data-width="390px">Mobile</button></div>
                        <iframe id="preview" title="Email preview" sandbox=""></iframe>
                    </div>
                </div>
            @endif
            <p class="muted small">Use <code>@{{ first_name | fallback }}</code> for a fallback value. Links are tracked automatically; an unsubscribe footer is always added.</p>
        </section>
        <div class="actions">
            @if ($campaign->exists)<a class="btn" href="{{ route('campaigns.review', $campaign) }}">Preview &amp; checklist</a>@endif
            <button class="btn btn-primary">Save</button>
        </div>
    </form>

    @if ($campaign->exists && $campaign->kind === 'rss')
        @include('campaigns._rss')
    @elseif ($campaign->exists && ! $campaign->isAutomation())
        @include('campaigns._ab_test')
    @endif

    @if ($campaign->exists)
        <div class="grid-2">
            <form method="post" action="{{ route('campaigns.test', $campaign) }}" class="card stack">
                @csrf
                <h2>Send a test</h2>
                <label>Email addresses <small class="muted">(comma-separated, max 5)</small><input name="emails" value="{{ old('emails', auth()->user()->email) }}"></label>
                <button class="btn">Send test</button>
            </form>
            @unless ($campaign->isAutomation())
            @if (auth()->user()->mayUse('campaigns.schedule', 'POST'))
<form method="post" action="{{ route('campaigns.schedule', $campaign) }}" class="card stack">
                @csrf
                <h2>Schedule</h2>
                <label>Send at <input type="datetime-local" name="scheduled_at" value="{{ $campaign->scheduled_at?->format('Y-m-d\TH:i') }}" required></label>
                <button class="btn">Schedule</button>
                @if ($campaign->status === \App\Enums\CampaignStatus::Scheduled)
                    <button class="btn btn-ghost" formaction="{{ route('campaigns.unschedule', $campaign) }}" formnovalidate>Unschedule</button>
                @endif
            </form>
@endif
            <form method="post" action="{{ route('templates.store') }}" class="card stack">
                @csrf
                <input type="hidden" name="campaign_id" value="{{ $campaign->id }}">
                <h2>Save as template</h2>
                <label>Template name <input name="name" required maxlength="120" value="{{ $campaign->name }}"></label>
                <button class="btn">Save template</button>
            </form>
            @if (auth()->user()->mayUse('campaigns.send', 'POST'))
<form method="post" action="{{ route('campaigns.send', $campaign) }}" class="card stack" onsubmit="return confirm('Send this campaign to {{ $recipientCount ?? 0 }} recipients now?')">
                @csrf
                <h2>Send now</h2>
                <p class="muted">Save your changes first. Sending can't be undone.</p>
                <button class="btn btn-primary" @disabled(($recipientCount ?? 0) === 0)>Send to {{ number_format($recipientCount ?? 0) }}</button>
            </form>
@endif
            @endunless
        </div>
    @endif
@endsection

@push('scripts')
<script src="{{ asset('js/campaign-editor.js') }}"></script>
<script src="{{ asset('js/email-builder.js') }}"></script>
@endpush
