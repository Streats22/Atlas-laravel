<div class="journey-add">
    <details>
        <summary aria-label="Add a step here">+</summary>
        <div class="add-menu">
            @foreach ($stepTypes as $type)
                <form method="post" action="{{ route('automations.steps.store', $automation) }}">
                    @csrf<input type="hidden" name="type" value="{{ $type->value }}"><input type="hidden" name="after" value="{{ $after }}">
                    <button class="btn small">{{ $type->label() }}</button>
                </form>
            @endforeach
        </div>
    </details>
</div>
