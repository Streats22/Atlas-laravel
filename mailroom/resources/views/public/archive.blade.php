@extends('layouts.public')
@section('title', 'Past emails')
@section('content')
    <h1>{{ $audience->name }}</h1>
    <p class="muted">Past emails</p>
    @forelse ($campaigns as $c)
        <div class="link-row row-between"><a href="{{ $c->webUrl() }}">{{ $c->subject }}</a><span class="muted small">{{ $c->sent_at?->format('M j, Y') }}</span></div>
    @empty
        <p class="empty">Nothing sent yet.</p>
    @endforelse
    <p><a class="btn btn-primary" href="{{ route('public.form', $audience->public_id) }}">Subscribe</a></p>
@endsection
