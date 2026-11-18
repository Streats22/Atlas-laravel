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
            <p class="muted small">Double opt-in is <strong>{{ $audience->double_opt_in ? 'on' : 'off' }}</strong>. <a href="{{ route('audiences.edit', $audience) }}">Change</a></p>
        </section>
        <section class="card stack">
            <h2>Embedded form</h2>
            <p class="muted">Paste this HTML into your website.</p>
            <textarea readonly rows="12" onclick="this.select()" aria-label="Embed code">{{ view('public._embed', ['audience' => $audience])->render() }}</textarea>
        </section>
    </div>
@endsection
