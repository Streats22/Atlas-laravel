@extends('layouts.app')
@section('title', $contact->exists ? $contact->email : 'Add contact')
@section('content')
    <header class="page-head"><div>
        <a class="muted small" href="{{ route('audiences.show', $audience) }}">← {{ $audience->name }}</a>
        <h1>{{ $contact->exists ? $contact->email : 'Add contact' }}</h1>
    </div></header>
    <div class="grid-2">
        <form method="post" action="{{ $contact->exists ? route('audiences.contacts.update', [$audience, $contact]) : route('audiences.contacts.store', $audience) }}" class="card stack">
            @csrf
            @if ($contact->exists) @method('put') @endif
            <label>Email <input type="email" name="email" value="{{ old('email', $contact->email) }}" required></label>
            <div class="grid-2">
                <label>First name <input name="first_name" value="{{ old('first_name', $contact->first_name) }}"></label>
                <label>Last name <input name="last_name" value="{{ old('last_name', $contact->last_name) }}"></label>
            </div>
            <label>Status
                <select name="status">@foreach (\App\Enums\ContactStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(old('status', $contact->status?->value) === $s->value)>{{ $s->label() }}</option>
                @endforeach</select>
            </label>
            <label>Tags <small class="muted">(comma-separated)</small><input name="tags" value="{{ old('tags', $contact->exists ? $contact->tags->pluck('name')->implode(', ') : '') }}"></label>
            @unless ($contact->exists)
                <p class="muted small">Only add people who gave you permission to email them.</p>
            @endunless
            <div class="row"><button class="btn btn-primary">Save</button></div>
        </form>
        @if ($contact->exists)
            <section class="card">
                <h2>Activity</h2>
                <dl class="meta">
                    <dt>Source</dt><dd>{{ ucfirst($contact->source) }}</dd>
                    <dt>Subscribed</dt><dd>{{ $contact->subscribed_at?->format('M j, Y H:i') ?? '—' }}</dd>
                    @if ($contact->confirmed_at)<dt>Confirmed</dt><dd>{{ $contact->confirmed_at->format('M j, Y H:i') }}</dd>@endif
                    @if ($contact->unsubscribed_at)<dt>Unsubscribed</dt><dd>{{ $contact->unsubscribed_at->format('M j, Y H:i') }}</dd>@endif
                </dl>
                @forelse ($contact->sends->sortByDesc('id') as $send)
                    <div class="row-between link-row"><a href="{{ route('campaigns.show', $send->campaign) }}">{{ $send->campaign->name }}</a>
                        <span class="muted small">{{ $send->opened_at ? 'opened' : ($send->sent_at ? 'sent' : 'queued') }}{{ $send->clicked_at ? ' · clicked' : '' }}</span></div>
                @empty
                    <p class="empty">No campaigns received yet.</p>
                @endforelse
                <form method="post" action="{{ route('audiences.contacts.destroy', [$audience, $contact]) }}" onsubmit="return confirm('Delete this contact permanently?')">
                    @csrf @method('delete') <button class="btn btn-ghost danger">Delete contact</button>
                </form>
            </section>
        @endif
    </div>
@endsection
