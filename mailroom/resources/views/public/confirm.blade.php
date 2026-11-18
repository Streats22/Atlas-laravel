@extends('layouts.public')
@section('title', 'Confirm your subscription')
@section('content')
    <h1>Confirm your subscription</h1>
    <p>Do you want to receive emails from <strong>{{ $audience->name }}</strong> at {{ $contact->email }}?</p>
    <form method="post" action="{{ $action }}">
        <button class="btn btn-primary">Yes, subscribe me</button>
    </form>
@endsection
