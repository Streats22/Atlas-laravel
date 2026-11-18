@php($rss = $campaign->rss ?? [])
<form method="post" action="{{ route('campaigns.rss', $campaign) }}" class="card stack" id="rss">
    @csrf @method('put')
    <div class="row-between">
        <h2>RSS feed</h2>
        <span class="badge badge-{{ $campaign->status === \App\Enums\CampaignStatus::Scheduled ? 'sent' : 'draft' }}">{{ $campaign->status === \App\Enums\CampaignStatus::Scheduled ? 'Running' : 'Paused' }}</span>
    </div>
    <p class="muted small">When the feed has new items, a campaign with those items is sent automatically. Put <code>@{{ rss_items }}</code> in the content where they belong and use <code>@{{ rss_title }}</code> in the subject for the feed title.</p>
    @if (! empty($rss['last_error']))<div class="flash flash-error">Last check failed: {{ $rss['last_error'] }}</div>@endif
    <label>Feed URL <input type="url" name="url" value="{{ old('url', $rss['url'] ?? '') }}" required placeholder="https://example.com/feed.xml"></label>
    <div class="grid-3">
        <label>Send
            <select name="frequency"><option value="daily" @selected(($rss['frequency'] ?? '') === 'daily')>Daily</option><option value="weekly" @selected(($rss['frequency'] ?? 'weekly') === 'weekly')>Weekly</option></select>
        </label>
        <label>On (weekly)
            <select name="weekday">@foreach ([1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $n => $d)<option value="{{ $n }}" @selected((int) ($rss['weekday'] ?? 1) === $n)>{{ $d }}</option>@endforeach</select>
        </label>
        <label>At
            <select name="hour">@for ($h = 0; $h < 24; $h++)<option value="{{ $h }}" @selected((int) ($rss['hour'] ?? 9) === $h)>{{ sprintf('%02d:00', $h) }}</option>@endfor</select>
        </label>
    </div>
    <div class="row">
        <button class="btn" name="action" value="save">Save</button>
        @if (auth()->user()->mayUse('campaigns.rss', 'PUT'))
            @if ($campaign->status === \App\Enums\CampaignStatus::Scheduled)
                <button class="btn" name="action" value="pause">Pause</button>
            @else
                <button class="btn btn-primary" name="action" value="start">Start sending</button>
            @endif
        @endif
        @if (! empty($rss['last_checked_at']))<span class="muted small">Last checked {{ \Illuminate\Support\Carbon::parse($rss['last_checked_at'])->diffForHumans() }}</span>@endif
    </div>
    @if ($campaign->relationLoaded('issues') && $campaign->issues->isNotEmpty())
        <h3>Sent issues</h3>
        @foreach ($campaign->issues as $issue)
            <div class="row-between link-row"><a href="{{ route('campaigns.show', $issue) }}">{{ $issue->name }}</a><span class="muted small">{{ $issue->recipients_count }} recipients</span></div>
        @endforeach
    @endif
</form>
