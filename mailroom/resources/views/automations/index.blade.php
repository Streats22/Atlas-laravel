@extends('layouts.app')
@section('title', 'Automations')
@section('content')
    <header class="page-head">
        <h1>Automations</h1>
        <a class="btn btn-primary" href="{{ route('automations.create') }}">Create automation</a>
    </header>
    <section class="card">
        @if ($automations->isEmpty())
            <p class="empty">Automations send emails by themselves: welcome new subscribers, follow up when a tag is added, and more.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Name</th><th>Trigger</th><th>Status</th><th>Steps</th><th>In journey</th><th>Total entered</th></tr></thead>
                <tbody>@foreach ($automations as $a)
                    <tr>
                        <td><a href="{{ route('automations.show', $a) }}"><strong>{{ $a->name }}</strong></a><div class="muted small">{{ $a->audience->name }}</div></td>
                        <td>{{ $a->triggerDescription() }}</td>
                        <td><span class="badge badge-{{ $a->status === 'active' ? 'sent' : ($a->status === 'paused' ? 'pending' : 'draft') }}">{{ ucfirst($a->status) }}</span></td>
                        <td>{{ $a->steps_count }}</td><td>{{ $a->active_runs_count }}</td><td>{{ $a->runs_count }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
@endsection
