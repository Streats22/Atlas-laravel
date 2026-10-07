<?php

declare(strict_types=1);

namespace Atlas\Http\Requests;

use Atlas\Blocks\BlockRegistry;
use Atlas\Blocks\FieldNormalizer;
use Atlas\Facades\Atlas;
use Atlas\Models\CustomBlock;
use Atlas\Support\Features;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the block builder's create / update payload. */
class SaveBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Features::customCode();
    }

    public function rules(): array
    {
        $existing = $this->route('block');
        $name = ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,40}$/'];
        $label = ['nullable', 'string', 'max:80'];

        return [
            'label' => ['required', 'string', 'max:80'],
            'type' => [
                $existing ? 'sometimes' : 'required', 'string', 'regex:' . BlockRegistry::TYPE_PATTERN,
                Rule::unique('atlas_blocks', 'type')->ignore($existing?->getKey()),
                $this->notTaken(...),
            ],
            'category' => ['nullable', 'string', 'max:40'],
            'icon' => ['nullable', 'string', 'max:8'],
            'container' => ['boolean'],
            'html' => ['nullable', 'string'],
            'css' => ['nullable', 'string'],
            'js' => ['nullable', 'string'],
            'fields' => ['array', 'max:40'],
            'fields.*.name' => [...$name, 'distinct'],
            'fields.*.label' => $label,
            'fields.*.type' => ['required', Rule::in(FieldNormalizer::TYPES)],
            'fields.*.default' => ['nullable'],
            'fields.*.translatable' => ['boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.language' => ['nullable', 'string', 'max:12'],
            'fields.*.fields' => ['nullable', 'array', 'max:12'],
            'fields.*.fields.*.name' => $name,
            'fields.*.fields.*.label' => $label,
            'fields.*.fields.*.type' => ['required', Rule::in(FieldNormalizer::SUB_TYPES)],
            'fields.*.fields.*.default' => ['nullable'],
            'fields.*.fields.*.translatable' => ['boolean'],
            'fields.*.fields.*.options' => ['nullable', 'array'],
        ];
    }

    /** A new block's machine name must not shadow a block defined in code. */
    private function notTaken(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! $this->route('block') && Atlas::blocks()->has((string) $value)) {
            $fail(__('atlas::ui.block_exists'));
        }
    }

    /** Validated data in the shape the CustomBlock model stores. */
    public function blockData(FieldNormalizer $fields): array
    {
        $data = $this->validated();
        $data['fields'] = $fields->normalizeAll($data['fields'] ?? []);
        $data['category'] = ($data['category'] ?? null) ?: 'Custom';
        $data['container'] = (bool) ($data['container'] ?? false);

        return $data;
    }
}
