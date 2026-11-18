@php($name = 'fields[' . $field->key . ']')
<label>{{ $field->label }}@if ($field->required) <span class="muted">*</span>@endif
    @switch($field->type->value)
        @case('dropdown')
            <select name="{{ $name }}" @required($field->required)>
                <option value="">—</option>
                @foreach ($field->options ?? [] as $option)<option @selected($value === $option)>{{ $option }}</option>@endforeach
            </select>
            @break
        @case('birthday')
            <input name="{{ $name }}" value="{{ $value }}" placeholder="MM-DD" pattern="(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])" @required($field->required)>
            @break
        @default
            <input type="{{ ['number' => 'number', 'date' => 'date', 'phone' => 'tel', 'url' => 'url'][$field->type->value] ?? 'text' }}" name="{{ $name }}" value="{{ $value }}" @required($field->required) @if ($field->type->value === 'number') step="any" @endif>
    @endswitch
</label>
