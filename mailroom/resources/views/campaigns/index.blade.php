@extends('layouts.app')
@section('title', 'Campaigns')
@section('content')
    <header class="page-head">
        <h1>Campaigns</h1>
        <a class="btn btn-primary" href="{{ route('campaigns.create') }}">Create campaign</a>
    </header>
    <nav class="tabs">
        <a href="{{ route('campaigns.index') }}" @class(['active' => ! $status])>All</a>
        @foreach (\App\Enums\CampaignStatus::cases() as $case)
            <a href="{{ route('campaigns.index', ['status' => $case->value]) }}" @class(['active' => $status === $case])>{{ $case->label() }}</a>
        @endforeach
    </nav>
    <section class="card">
        @include('campaigns._table')
        {{ $campaigns->links('partials.pagination') }}
    </section>
@endsection
