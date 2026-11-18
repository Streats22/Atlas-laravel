@extends('layouts.app')
@section('title', 'Import contacts')
@section('content')
    @include('audiences._nav')
    <form method="post" action="{{ route('audiences.import.store', $audience) }}" enctype="multipart/form-data" class="card stack narrow">
        @csrf
        <h2>Import from CSV</h2>
        <p class="muted">The first row must be a header. Recognised columns: <code>email</code> (required), <code>first_name</code>, <code>last_name</code>, <code>tags</code>. Comma or semicolon separated. Existing contacts are updated, never resubscribed.</p>
        <label>CSV file <input type="file" name="file" accept=".csv,text/csv" required></label>
        <label>Import as
            <select name="status"><option value="subscribed">Subscribed</option><option value="unsubscribed">Unsubscribed</option></select>
        </label>
        <label>Tag all imported contacts <small class="muted">(optional)</small><input name="tag" maxlength="60"></label>
        <label class="check"><input type="checkbox" name="consent" value="1" required> These people gave me permission to email them.</label>
        <div class="row"><button class="btn btn-primary">Import</button></div>
    </form>
@endsection
