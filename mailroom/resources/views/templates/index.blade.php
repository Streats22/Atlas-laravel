@extends('layouts.app')
@section('title', 'Templates')
@section('content')
    <header class="page-head"><h1>Email templates</h1></header>
    @if ($templates->isEmpty())
        <section class="card"><p class="empty">No saved templates yet. Open a campaign and use “Save as template”.</p></section>
    @else
        <div class="template-grid">
            @foreach ($templates as $t)
                <section class="card template-card">
                    <iframe src="{{ route('templates.show', $t) }}" sandbox="" title="{{ $t->name }}" loading="lazy" tabindex="-1"></iframe>
                    <div class="row-between">
                        <div><strong>{{ $t->name }}</strong><div class="muted small">{{ $t->design ? 'Drag & drop' : 'Code' }} · {{ $t->updated_at->format('M j, Y') }}</div></div>
                        <div class="row">
                            <a class="btn small" href="{{ route('campaigns.create', ['template' => 'saved:' . $t->id]) }}">Use</a>
                            <form method="post" action="{{ route('templates.destroy', $t) }}" onsubmit="return confirm('Delete this template?')">@csrf @method('delete')<button class="btn btn-ghost small danger">Delete</button></form>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection
