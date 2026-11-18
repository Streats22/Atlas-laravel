@extends('layouts.app')
@section('title', 'Landing pages')
@section('content')
    <header class="page-head">
        <h1>Landing pages</h1>
        <a class="btn btn-primary" href="{{ route('landing-pages.create') }}">Create landing page</a>
    </header>
    <section class="card">
        @if ($pages->isEmpty())
            <p class="empty">Landing pages are single web pages with a signup form: great for ads, social bios and launches.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Page</th><th>Status</th><th>Visits</th><th>Signups</th><th>Conversion</th></tr></thead>
                <tbody>@foreach ($pages as $p)
                    <tr>
                        <td><a href="{{ route('landing-pages.edit', $p) }}"><strong>{{ $p->title }}</strong></a><div class="muted small">/p/{{ $p->slug }} · {{ $p->audience->name }}</div></td>
                        <td><span class="badge badge-{{ $p->published ? 'sent' : 'draft' }}">{{ $p->published ? 'Published' : 'Draft' }}</span></td>
                        <td>{{ number_format($p->visits) }}</td><td>{{ number_format($p->signups) }}</td><td>{{ $p->conversionRate() }}%</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
@endsection
