@extends('layouts.app')
@section('title', 'Segments')
@section('content')
    @include('audiences._nav')
    <section class="card">
        <div class="row-between"><h2>Segments</h2><a class="btn btn-primary" href="{{ route('audiences.segments.create', $audience) }}">New segment</a></div>
        @if ($segments->isEmpty())
            <p class="empty">Segments are saved filters, e.g. “opened any campaign” or “tag is VIP”. Target a campaign at one to send only to them.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Name</th><th>Conditions</th><th>Subscribed contacts</th></tr></thead>
                <tbody>@foreach ($segments as $s)
                    <tr><td><a href="{{ route('audiences.segments.edit', [$audience, $s]) }}"><strong>{{ $s->name }}</strong></a></td>
                        <td>{{ count($s->conditions) }} ({{ $s->match }})</td><td>{{ number_format($s->size) }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
@endsection
