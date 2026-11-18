@php($audienceList = isset($audiences) ? $audiences : collect([$automation->audience]))
<label>Trigger
    <select name="trigger" class="trigger-select">
        @foreach (\App\Enums\AutomationTrigger::cases() as $t)
            <option value="{{ $t->value }}" @selected(old('trigger', $automation?->trigger?->value ?? 'subscribed') === $t->value)>{{ $t->label() }}</option>
        @endforeach
    </select>
</label>
<label class="trigger-tag">Tag name <input name="trigger_value" value="{{ old('trigger_value', $automation?->trigger?->value === 'tag_added' ? $automation->trigger_value : '') }}" maxlength="60" placeholder="e.g. customer"></label>
<label class="trigger-date">Date field
    <select name="trigger_field">
        <option value="">—</option>
        @foreach ($audienceList as $a)
            @foreach ($a->fields->filter(fn ($f) => $f->type->isDate()) as $f)
                <option value="{{ $f->key }}" data-audience="{{ $a->id }}" @selected(old('trigger_field', $automation?->trigger_value) === $f->key)>{{ $f->label }}@if ($audienceList->count() > 1) ({{ $a->name }})@endif</option>
            @endforeach
        @endforeach
    </select>
    <small class="muted">Runs every year on that month and day. No date fields? Add one under Audience → Fields.</small>
</label>
