@extends('layouts.app')
@section('title', $automation->name)
@section('content')
    <header class="page-head">
        <div>
            <a class="muted small" href="{{ route('automations.index') }}">← Automations</a>
            <h1>{{ $automation->name }}</h1>
            <span class="badge badge-{{ $automation->isActive() ? 'sent' : ($automation->status === 'paused' ? 'pending' : 'draft') }}">{{ ucfirst($automation->status) }}</span>
            <span class="muted small">{{ $automation->audience->name }}</span>
        </div>
        @if (auth()->user()->mayUse('automations.status', 'POST'))
<form method="post" action="{{ route('automations.status', $automation) }}">
            @csrf
            @if ($automation->isActive())
                <input type="hidden" name="status" value="paused"><button class="btn">Pause</button>
            @else
                <input type="hidden" name="status" value="active"><button class="btn btn-primary">Turn on</button>
            @endif
        </form>
@endif
    </header>

    <div class="stats">
        <div class="stat"><span>In journey now</span><strong>{{ number_format($counts['active']) }}</strong></div>
        <div class="stat"><span>Completed</span><strong>{{ number_format($counts['completed']) }}</strong></div>
        <div class="stat"><span>Exited (unsubscribed)</span><strong>{{ number_format($counts['exited']) }}</strong></div>
    </div>

    <div class="review">
        <section class="journey" aria-label="Journey">
            <div class="journey-node trigger-node">
                <span class="node-kind">Trigger</span>
                <strong>{{ $automation->triggerDescription() }}</strong>
            </div>
            @include('automations._add', ['after' => -1])
            @foreach ($automation->steps as $i => $step)
                <div class="journey-node node-{{ $step->type->value }}">
                    <div class="row-between">
                        <span class="node-kind">{{ $step->type->label() }}</span>
                        <div class="row node-tools">
                            @if (($waiting[$i + 1] ?? 0) > 0 && $step->type === \App\Enums\StepType::Wait)<span class="badge badge-pending">{{ $waiting[$i + 1] }} waiting</span>@endif
                            <form method="post" action="{{ route('automations.steps.move', [$automation, $step]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="link" @disabled($i === 0) aria-label="Move up">↑</button></form>
                            <form method="post" action="{{ route('automations.steps.move', [$automation, $step]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="link" @disabled($loop->last) aria-label="Move down">↓</button></form>
                            <form method="post" action="{{ route('automations.steps.destroy', [$automation, $step]) }}" onsubmit="return confirm('Remove this step?')">@csrf @method('delete')<button class="link danger" aria-label="Remove step">✕</button></form>
                        </div>
                    </div>
                    @switch($step->type)
                        @case(\App\Enums\StepType::Email)
                            @php($stats = $step->campaign?->stats())
                            <strong>{{ $step->campaign?->subject }}</strong>
                            @if ($stats)
                                <div class="muted small">{{ $stats['sent'] }} sent · {{ $stats['open_rate'] }}% opened · {{ $stats['click_rate'] }}% clicked</div>
                            @endif
                            <div class="row"><a class="btn small" href="{{ route('campaigns.edit', $step->campaign_id) }}">Design email</a><a class="btn btn-ghost small" href="{{ route('campaigns.review', $step->campaign_id) }}">Preview</a></div>
                            @break
                        @case(\App\Enums\StepType::Wait)
                            <form method="post" action="{{ route('automations.steps.update', [$automation, $step]) }}" class="row">
                                @csrf @method('put')
                                <input type="number" name="amount" min="0" max="1000" value="{{ $step->config['amount'] ?? 1 }}" aria-label="Amount" class="w-num">
                                <select name="unit" aria-label="Unit">@foreach (['minutes', 'hours', 'days', 'weeks'] as $u)<option @selected(($step->config['unit'] ?? 'days') === $u)>{{ $u }}</option>@endforeach</select>
                                <button class="btn small">Save</button>
                            </form>
                            @break
                        @default
                            <form method="post" action="{{ route('automations.steps.update', [$automation, $step]) }}" class="row">
                                @csrf @method('put')
                                <input name="tag" value="{{ $step->config['tag'] ?? '' }}" required maxlength="60" aria-label="Tag" placeholder="Tag name" class="w-tag">
                                <button class="btn small">Save</button>
                            </form>
                    @endswitch
                </div>
                @include('automations._add', ['after' => $i])
            @endforeach
            <div class="journey-node end-node"><span class="node-kind">End of journey</span></div>
        </section>

        <div class="stack">
            <form method="post" action="{{ route('automations.update', $automation) }}" class="card stack">
                @csrf @method('put')
                <h2>Settings</h2>
                <label>Name <input name="name" value="{{ old('name', $automation->name) }}" required maxlength="150"></label>
                @include('automations._trigger_fields')
                <button class="btn">Save</button>
            </form>
            <form method="post" action="{{ route('automations.enroll', $automation) }}" class="card stack">
                @csrf
                <h2>Add existing contacts</h2>
                <p class="muted small">New contacts enter automatically through the trigger. Use this to put current subscribers through the journey too.</p>
                <label>Who
                    <select name="segment_id"><option value="">All subscribed contacts</option>
                        @foreach ($automation->audience->segments as $s)<option value="{{ $s->id }}">Segment: {{ $s->name }}</option>@endforeach
                    </select>
                </label>
                <button class="btn" @disabled(! $automation->isActive())>Add contacts</button>
                @unless ($automation->isActive())<p class="muted small">Turn the automation on first.</p>@endunless
            </form>
            <form method="post" action="{{ route('automations.destroy', $automation) }}" onsubmit="return confirm('Delete this automation and its emails?')">
                @csrf @method('delete')<button class="btn btn-ghost danger">Delete automation</button>
            </form>
        </div>
    </div>
@endsection
@push('scripts')<script src="{{ asset('js/automations.js') }}"></script>@endpush
