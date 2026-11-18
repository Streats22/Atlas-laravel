@if (session('status'))
    <div class="flash" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="flash flash-error" role="alert">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif
