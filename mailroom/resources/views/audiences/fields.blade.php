@extends('layouts.app')
@section('title', 'Fields · ' . $audience->name)
@section('content')
    @include('audiences._nav')
    <div class="grid-2">
        <section class="card">
            <h2>Audience fields</h2>
            <p class="muted small">Built-in: email, first_name, last_name. Add your own to collect more data, personalise emails with merge tags, build segments and trigger birthday automations.</p>
            @forelse ($fields as $field)
                <details class="field-row">
                    <summary>
                        <strong>{{ $field->label }}</strong>
                        <code>@{{ {{ $field->key }} }}</code>
                        <span class="muted small">{{ $field->type->label() }}{{ $field->required ? ' · required' : '' }}{{ $field->show_on_form ? '' : ' · hidden on form' }}</span>
                    </summary>
                    <form method="post" action="{{ route('audiences.fields.update', [$audience, $field]) }}" class="stack">
                        @csrf @method('put')
                        <input type="hidden" name="type" value="{{ $field->type->value }}">
                        <label>Label <input name="label" value="{{ $field->label }}" required maxlength="80"></label>
                        @if ($field->type->value === 'dropdown')
                            <label>Options <small class="muted">(one per line)</small><textarea name="options" rows="4">{{ implode("\n", $field->options ?? []) }}</textarea></label>
                        @endif
                        <label class="check"><input type="checkbox" name="required" value="1" @checked($field->required)> Required</label>
                        <label class="check"><input type="checkbox" name="show_on_form" value="1" @checked($field->show_on_form)> Show on signup form</label>
                        <div class="row"><button class="btn small">Save</button></div>
                    </form>
                    <form method="post" action="{{ route('audiences.fields.destroy', [$audience, $field]) }}" onsubmit="return confirm('Remove this field?')">
                        @csrf @method('delete')<button class="btn btn-ghost small danger">Remove field</button>
                    </form>
                </details>
            @empty
                <p class="empty">No custom fields yet.</p>
            @endforelse
        </section>
        <form method="post" action="{{ route('audiences.fields.store', $audience) }}" class="card stack">
            @csrf
            <h2>Add a field</h2>
            <label>Label <input name="label" value="{{ old('label') }}" required maxlength="80" placeholder="e.g. Birthday, Company, City"></label>
            <label>Type
                <select name="type" id="field-type">@foreach (\App\Enums\FieldType::cases() as $t)<option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>@endforeach</select>
            </label>
            <label id="field-options" hidden>Options <small class="muted">(one per line)</small><textarea name="options" rows="4">{{ old('options') }}</textarea></label>
            <label>Merge tag <small class="muted">(optional, generated from the label)</small><input name="key" value="{{ old('key') }}" pattern="[a-z][a-z0-9_]*" maxlength="40" placeholder="company"></label>
            <label class="check"><input type="checkbox" name="required" value="1"> Required</label>
            <label class="check"><input type="checkbox" name="show_on_form" value="1" checked> Show on signup form</label>
            <div class="row"><button class="btn btn-primary">Add field</button></div>
        </form>
    </div>
@endsection
@push('scripts')
<script>
(() => {
    const type = document.getElementById('field-type');
    const sync = () => { document.getElementById('field-options').hidden = type.value !== 'dropdown'; };
    type.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
