@extends('layouts.public')
@section('title', 'Unsubscribe')
@section('content')
    @if ($contact->status === \App\Enums\ContactStatus::Unsubscribed)
        <h1>You're already unsubscribed</h1>
        <p>{{ $contact->email }} no longer receives emails from {{ $audience->name }}.</p>
    @else
        <h1>Unsubscribe</h1>
        <p>Stop receiving emails from <strong>{{ $audience->name }}</strong> at {{ $contact->email }}?</p>
        <form method="post" action="{{ route('public.unsubscribe.do', ['token' => $contact->token, 'send' => request('send')]) }}">
            <button class="btn btn-primary">Unsubscribe</button>
        </form>
    @endif
@endsection
