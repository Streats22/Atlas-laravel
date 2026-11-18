@extends('layouts.app')
@section('title', $campaign->name . ' report')
@section('content')
    <header class="page-head">
        <div>
            <a class="muted small" href="{{ route('campaigns.index') }}">← Campaigns</a>
            <h1>{{ $campaign->name }}</h1>
            <span class="badge badge-{{ $campaign->status->value }}">{{ $campaign->status->label() }}</span>
            <span class="muted small">{{ $campaign->sent_at?->format('M j, Y H:i') }} · {{ $campaign->audience->name }}@if ($campaign->segment) · segment “{{ $campaign->segment->name }}”@endif @if ($campaign->tag) · tag “{{ $campaign->tag->name }}”@endif</span>
        </div>
        <div class="row">
            <a class="btn btn-ghost" href="{{ route('campaigns.preview', $campaign) }}" target="_blank" rel="noopener">View email</a>
            <form method="post" action="{{ route('campaigns.duplicate', $campaign) }}">@csrf<button class="btn">Replicate</button></form>
        </div>
    </header>

    <div class="stats">
        <div class="stat"><span>Recipients</span><strong>{{ number_format($campaign->recipients_count) }}</strong><small>{{ $stats['sent'] }} delivered · {{ $stats['failed'] }} failed</small></div>
        <div class="stat"><span>Open rate</span><strong>{{ $stats['open_rate'] }}%</strong><small>{{ $stats['opens'] }} unique opens</small><div class="bar"><i style="width: {{ min(100, $stats['open_rate']) }}%"></i></div></div>
        <div class="stat"><span>Click rate</span><strong>{{ $stats['click_rate'] }}%</strong><small>{{ $stats['clicks'] }} unique clicks</small><div class="bar"><i style="width: {{ min(100, $stats['click_rate']) }}%"></i></div></div>
        <div class="stat"><span>Unsubscribed</span><strong>{{ $stats['unsubscribes'] }}</strong></div>
    </div>

    @if ($campaign->variants->isNotEmpty())
        <section class="card">
            <div class="row-between">
                <h2>A/B test: {{ \App\Services\AbTest::VARIABLES[$campaign->ab_test['variable'] ?? 'subject'] ?? '' }}</h2>
                @if ($campaign->status === \App\Enums\CampaignStatus::Testing)
                    <span class="muted small">
                        @if (($campaign->ab_test['winner_by'] ?? '') === 'manual') Pick a winner to send it to the remaining {{ number_format(($campaign->ab_test['total'] ?? 0) - $campaign->recipients_count) }} recipients.
                        @else Winner by {{ $campaign->ab_test['winner_by'] }} is chosen {{ \Illuminate\Support\Carbon::parse($campaign->ab_test['decide_at'])->diffForHumans() }}.
                        @endif
                    </span>
                @endif
            </div>
            <div class="table-wrap"><table>
                <thead><tr><th>Variant</th><th>Tested</th><th>Sent</th><th>Open rate</th><th>Click rate</th><th></th></tr></thead>
                <tbody>@foreach ($campaign->variants as $v)
                    @php($vs = $v->stats())
                    <tr>
                        <td><strong>{{ $v->label }}</strong> @if ($v->is_winner)<span class="badge badge-sent">Winner</span>@endif</td>
                        <td class="truncate">{{ match ($campaign->ab_test['variable'] ?? '') { 'subject' => $v->subject ?: $campaign->subject, 'from_name' => $v->from_name ?: $campaign->from_name, 'send_time' => '+' . $v->send_delay_hours . 'h', default => 'Content ' . $v->label } }}</td>
                        <td>{{ $vs['sent'] }}</td><td>{{ $vs['open_rate'] }}%</td><td>{{ $vs['click_rate'] }}%</td>
                        <td class="right">@if ($campaign->status === \App\Enums\CampaignStatus::Testing)
                            <form method="post" action="{{ route('campaigns.variants.winner', [$campaign, $v]) }}" onsubmit="return confirm('Send variant {{ $v->label }} to everyone else?')">@csrf<button class="btn small">Send this one</button></form>
                        @endif</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        </section>
    @endif

    <div class="grid-2">
        <section class="card">
            <h2>Opens over time</h2>
            @if ($opensByHour->isEmpty())
                <p class="empty">No opens yet.</p>
            @else
                @php($max = max(1, $opensByHour->max()))
                <div class="histogram" role="img" aria-label="Opens per hour">
                    @foreach ($opensByHour as $hour => $count)
                        <div class="col" title="{{ $hour }}: {{ $count }}"><i style="height: {{ (int) round($count / $max * 100) }}%"></i></div>
                    @endforeach
                </div>
                <div class="row-between muted small"><span>{{ $opensByHour->keys()->first() }}</span><span>{{ $opensByHour->keys()->last() }}</span></div>
            @endif
        </section>
        <section class="card">
            <h2>Top links</h2>
            @forelse ($links as $link)
                <div class="row-between link-row"><span class="truncate">{{ $link->url }}</span><strong>{{ $link->click_count }}</strong></div>
            @empty
                <p class="empty">No links in this email.</p>
            @endforelse
        </section>
    </div>

    <section class="card">
        <h2>Recent activity</h2>
        @if ($recent->isEmpty())
            <p class="empty">Nobody has opened this campaign yet.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Contact</th><th>Opened</th><th>Opens</th><th>Clicks</th></tr></thead>
                <tbody>@foreach ($recent as $send)
                    <tr><td>{{ $send->contact->email }}</td><td>{{ $send->opened_at->diffForHumans() }}</td><td>{{ $send->open_count }}</td><td>{{ $send->click_count }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
@endsection
