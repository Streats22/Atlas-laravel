<label>Trigger
    <select name="trigger" class="trigger-select">
        @foreach (\App\Enums\AutomationTrigger::cases() as $t)
            <option value="{{ $t->value }}" @selected(old('trigger', $automation?->trigger?->value ?? $default ?? 'subscribed') === $t->value)>{{ $t->label() }}</option>
        @endforeach
    </select>
</label>
<label class="trigger-tag">Tag name <input name="trigger_value" value="{{ old('trigger_value', $automation?->trigger_value) }}" maxlength="60" placeholder="e.g. customer"></label>
