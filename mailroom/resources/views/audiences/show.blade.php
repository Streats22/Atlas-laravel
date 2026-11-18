@extends('layouts.app')
@section('title', $audience->name)
@section('content')
    @include('audiences._nav')
    <div class="stats">
        @foreach (\App\Enums\ContactStatus::cases() as $s)
            <a class="stat" href="{{ route('audiences.show', [$audience, 'status' => $s->value]) }}"><span>{{ $s->label() }}</span><strong>{{ number_format($counts[$s->value] ?? 0) }}</strong></a>
        @endforeach
    </div>

    <section class="card">
        <div class="row-between"><h2>Audience growth</h2><span class="muted small">new contacts per week</span></div>
        @php($max = max(1, max($growth)))
        <div class="histogram growth" role="img" aria-label="New contacts per week, last 12 weeks">
            @foreach ($growth as $week => $count)<div class="col" title="Week of {{ $week }}: {{ $count }}"><i style="height: {{ (int) round($count / $max * 100) }}%"></i></div>@endforeach
        </div>
        <div class="row-between muted small"><span>{{ array_key_first($growth) }}</span><span>{{ array_key_last($growth) }}</span></div>
    </section>

    <section class="card">
        <form method="get" class="filters">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search email or name" aria-label="Search">
            <select name="status" aria-label="Status"><option value="">Any status</option>
                @foreach (\App\Enums\ContactStatus::cases() as $s)<option value="{{ $s->value }}" @selected($filters['status'] === $s->value)>{{ $s->label() }}</option>@endforeach
            </select>
            <select name="tag" aria-label="Tag"><option value="">Any tag</option>
                @foreach ($tags as $t)<option value="{{ $t->id }}" @selected($filters['tag'] === $t->id)>{{ $t->name }} ({{ $t->contacts_count }})</option>@endforeach
            </select>
            <button class="btn">Filter</button>
            <span class="grow"></span>
            <a class="btn btn-ghost" href="{{ route('audiences.export', $audience) }}">Export CSV</a>
            <a class="btn btn-primary" href="{{ route('audiences.contacts.create', $audience) }}">Add contact</a>
        </form>

        @if ($contacts->isEmpty())
            <p class="empty">No contacts match. <a href="{{ route('audiences.import', $audience) }}">Import a CSV</a> or <a href="{{ route('audiences.form', $audience) }}">share your signup form</a>.</p>
        @else
            <form method="post" action="{{ route('audiences.contacts.bulk', $audience) }}" id="bulk">
                @csrf
                <div class="bulk-bar">
                    <select name="action" aria-label="Bulk action">
                        <option value="tag">Add tag</option><option value="untag">Remove tag</option>
                        <option value="unsubscribe">Unsubscribe</option><option value="delete">Delete</option>
                    </select>
                    <input name="tag" placeholder="Tag name" aria-label="Tag name" list="tag-names">
                    <datalist id="tag-names">@foreach ($tags as $t)<option value="{{ $t->name }}">@endforeach</datalist>
                    <button class="btn" onclick="return confirm('Apply to the selected contacts?')">Apply to selected</button>
                </div>
                <div class="table-wrap"><table>
                    <thead><tr><th><input type="checkbox" aria-label="Select all" onclick="document.querySelectorAll('#bulk [name=&quot;ids[]&quot;]').forEach(c => c.checked = this.checked)"></th><th>Email</th><th>Name</th><th>Status</th><th>Rating</th><th>Tags</th><th>Added</th></tr></thead>
                    <tbody>@foreach ($contacts as $c)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $c->id }}" aria-label="Select {{ $c->email }}"></td>
                            <td><a href="{{ route('audiences.contacts.edit', [$audience, $c]) }}">{{ $c->email }}</a></td>
                            <td>{{ $c->fullName() }}</td>
                            <td><span class="badge badge-{{ $c->status->value }}">{{ $c->status->label() }}</span></td>
                            @php($stars = $c->rating($c->received_count, $c->opened_count, $c->clicked_count))
                            <td><span class="stars" aria-label="{{ $stars }} of 5 stars">{{ str_repeat('★', $stars) }}<span class="off">{{ str_repeat('★', 5 - $stars) }}</span></span></td>
                            <td>@foreach ($c->tags as $t)<span class="chip static">{{ $t->name }}</span>@endforeach</td>
                            <td class="muted small">{{ $c->created_at->format('M j, Y') }}</td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
            </form>
            {{ $contacts->links('partials.pagination') }}
        @endif
    </section>
@endsection
