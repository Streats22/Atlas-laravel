@extends('layouts.app')
@section('title', 'Signup form')
@section('content')
    @include('audiences._nav')
    @php($url = route('public.form', $audience->public_id))
    <div class="grid-2">
        <section class="card stack">
            <h2>Hosted form</h2>
            <p class="muted">Share this link anywhere: social bios, QR codes, email signatures.</p>
            <input readonly value="{{ $url }}" onclick="this.select()" aria-label="Form URL">
            <a class="btn" href="{{ $url }}" target="_blank" rel="noopener">Open form</a>
            <p class="muted small">Public archive of past campaigns: <a href="{{ route('public.archive', $audience->public_id) }}" target="_blank" rel="noopener">{{ route('public.archive', $audience->public_id) }}</a></p>
            <p class="muted small">Double opt-in is <strong>{{ $audience->double_opt_in ? 'on' : 'off' }}</strong>. <a href="{{ route('audiences.edit', $audience) }}">Change</a></p>
        </section>
        <section class="card stack">
            <h2>Embedded form</h2>
            <p class="muted">Paste this HTML into your website.</p>
            <textarea readonly rows="12" onclick="this.select()" aria-label="Embed code">{{ view('public._embed', ['audience' => $audience])->render() }}</textarea>
        </section>
        <section class="card stack">
            <h2>Pop-up form</h2>
            <p class="muted">Shows your signup form in a pop-up after a few seconds, once per visitor. Paste before <code>&lt;/body&gt;</code>:</p>
            <textarea readonly rows="3" onclick="this.select()" aria-label="Pop-up code">&lt;script src="{{ route('public.popup', $audience->public_id) }}" data-delay="5" async&gt;&lt;/script&gt;</textarea>
            <p class="muted small">Change <code>data-delay</code> to set the delay in seconds.</p>
        </section>
    </div>
@endsection
