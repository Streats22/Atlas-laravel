@extends('layouts.app')
@section('title', 'Audiences')
@section('content')
    <header class="page-head">
        <h1>Audiences</h1>
        <a class="btn btn-primary" href="{{ route('audiences.create') }}">Create audience</a>
    </header>
    <section class="card">
        @if ($audiences->isEmpty())
            <p class="empty">No audiences yet. An audience is a list of contacts you send campaigns to.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Name</th><th>Subscribed</th><th>Total contacts</th><th>Double opt-in</th></tr></thead>
                <tbody>@foreach ($audiences as $a)
                    <tr><td><a href="{{ route('audiences.show', $a) }}"><strong>{{ $a->name }}</strong></a></td>
                        <td>{{ number_format($a->subscribed_count) }}</td><td>{{ number_format($a->contacts_count) }}</td>
                        <td>{{ $a->double_opt_in ? 'Yes' : 'No' }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
@endsection
