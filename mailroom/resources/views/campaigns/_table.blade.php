@if ($campaigns->isEmpty())
    <p class="empty">No campaigns yet. <a href="{{ route('campaigns.create') }}">Create your first one</a>.</p>
@else
    <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Audience</th><th>Status</th><th>Opens</th><th>Clicks</th><th></th></tr></thead>
        <tbody>
        @foreach ($campaigns as $c)
            @php($s = $c->status->isEditable() ? null : $c->stats())
            <tr>
                <td><a href="{{ route('campaigns.show', $c) }}"><strong>{{ $c->name }}</strong></a>@if ($c->kind === 'rss') <span class="badge">RSS</span>@endif<div class="muted small">{{ $c->subject }}</div></td>
                <td>{{ $c->audience->name }}</td>
                <td><span class="badge badge-{{ $c->status->value }}">{{ $c->status->label() }}</span>
                    <div class="muted small">
                        @if ($c->sent_at) {{ $c->sent_at->format('M j, Y H:i') }} @elseif ($c->scheduled_at) {{ $c->scheduled_at->format('M j, Y H:i') }} @else edited {{ $c->updated_at->diffForHumans() }} @endif
                    </div></td>
                <td>{{ $s ? $s['open_rate'] . '%' : '—' }}</td>
                <td>{{ $s ? $s['click_rate'] . '%' : '—' }}</td>
                <td class="right">
                    <form method="post" action="{{ route('campaigns.duplicate', $c) }}">@csrf<button class="link">Duplicate</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
