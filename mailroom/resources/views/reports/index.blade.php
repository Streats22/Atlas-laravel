@extends('layouts.app')
@section('title', 'Reports')
@section('content')
    <header class="page-head">
        <h1>Reports</h1>
        <form method="get" class="row">
            <select name="audience" onchange="this.form.submit()" aria-label="Audience">
                <option value="">All audiences</option>
                @foreach ($audiences as $a)<option value="{{ $a->id }}" @selected($audienceId === $a->id)>{{ $a->name }}</option>@endforeach
            </select>
            <a class="btn" href="{{ route('reports.export', ['audience' => $audienceId]) }}">Export CSV</a>
        </form>
    </header>
    <div class="stats">
        <div class="stat"><span>Campaigns sent</span><strong>{{ $totals['campaigns'] }}</strong><small>{{ number_format($totals['sent']) }} emails</small></div>
        <div class="stat"><span>Avg. open rate</span><strong>{{ $totals['open_rate'] }}%</strong></div>
        <div class="stat"><span>Avg. click rate</span><strong>{{ $totals['click_rate'] }}%</strong></div>
        <div class="stat"><span>Unsubscribe rate</span><strong>{{ $totals['unsub_rate'] }}%</strong></div>
        <div class="stat"><span>Bounce rate</span><strong>{{ $totals['bounce_rate'] }}%</strong></div>
    </div>
    @if ($rows->count() > 1)
        <section class="card">
            <h2>Open and click rate per campaign</h2>
            <div class="compare-chart" role="img" aria-label="Open and click rate per campaign, oldest to newest">
                @foreach ($rows->reverse() as $r)
                    <div class="compare-col" title="{{ $r['name'] }}: {{ $r['open_rate'] }}% opens, {{ $r['click_rate'] }}% clicks">
                        <i class="open" style="height: {{ (int) min(100, $r['open_rate']) }}%"></i><i class="click" style="height: {{ (int) min(100, $r['click_rate']) }}%"></i>
                    </div>
                @endforeach
            </div>
            <div class="row muted small"><span class="legend open"></span> Open rate <span class="legend click"></span> Click rate</div>
        </section>
    @endif
    <section class="card">
        <h2>Compare campaigns</h2>
        @if ($rows->isEmpty())
            <p class="empty">No sent campaigns yet.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Campaign</th><th>Sent</th><th>Opens</th><th>Clicks</th><th>Unsubs</th><th>Bounces</th></tr></thead>
                <tbody>@foreach ($rows as $r)
                    <tr>
                        <td><a href="{{ route('campaigns.show', $r['id']) }}"><strong>{{ $r['name'] }}</strong></a><div class="muted small">{{ $r['audience'] }} · {{ $r['sent_at'] }}</div></td>
                        <td>{{ number_format($r['sent']) }}</td>
                        <td @class(['good' => $r['open_rate'] > $totals['open_rate'], 'bad' => $r['open_rate'] < $totals['open_rate']])>{{ $r['open_rate'] }}%</td>
                        <td @class(['good' => $r['click_rate'] > $totals['click_rate'], 'bad' => $r['click_rate'] < $totals['click_rate']])>{{ $r['click_rate'] }}%</td>
                        <td>{{ $r['unsubscribes'] }}</td><td>{{ $r['bounces'] }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            <p class="muted small">Green/red: above/below your average.</p>
        @endif
    </section>
@endsection
