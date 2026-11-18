@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    <header class="page-head">
        <h1>Dashboard</h1>
        <a class="btn btn-primary" href="{{ route('campaigns.create') }}">Create campaign</a>
    </header>
    <div class="stats">
        <div class="stat"><span>Subscribers</span><strong>{{ number_format($subscribers) }}</strong><small>{{ $newThisMonth }} new this month</small></div>
        <div class="stat"><span>Emails sent</span><strong>{{ number_format($sent) }}</strong><small>across {{ $audiences }} {{ Str::plural('audience', $audiences) }}</small></div>
        <div class="stat"><span>Avg. open rate</span><strong>{{ $openRate }}%</strong></div>
        <div class="stat"><span>Avg. click rate</span><strong>{{ $clickRate }}%</strong></div>
    </div>
    <section class="card">
        <h2>Recent campaigns</h2>
        @include('campaigns._table', ['campaigns' => $recent])
    </section>
@endsection
