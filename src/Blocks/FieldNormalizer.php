<?php

declare(strict_types=1);

namespace Atlas\Blocks;

/** Turns builder form input into the canonical field definitions blocks use. */
final class FieldNormalizer
{
    public const TYPES = ['text', 'textarea', 'number', 'select', 'color', 'checkbox', 'image', 'code', 'repeater'];

    /** Repeater sub-fields cannot nest further. */
    public const SUB_TYPES = ['text', 'textarea', 'number', 'select', 'color', 'checkbox', 'image'];

    /** @param  list<array<string, mixed>>  $fields */
    public function normalizeAll(array $fields): array
    {
        return array_map($this->normalize(...), $fields);
    }

    /** @param  array<string, mixed>  $input */
    public function normalize(array $input): array
    {
        $type = $input['type'];
        $field = [
            'type' => $type,
            'name' => $input['name'],
            'label' => ($input['label'] ?? null) ?: ucfirst(str_replace('_', ' ', $input['name'])),
            'default' => $this->default($type, $input['default'] ?? null),
        ];

        return match ($type) {
            'repeater' => $this->repeater($field, $input),
            'select' => $this->select($field, $input),
            'code' => $field + ['language' => $input['language'] ?? 'html'],
            'text', 'textarea' => $field + (empty($input['translatable']) ? [] : ['translatable' => true]),
            default => $field,
        };
    }

    private function default(string $type, mixed $value): mixed
    {
        return match ($type) {
            'number' => is_numeric($value) ? $value + 0 : 0,
            'checkbox' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'repeater' => is_array($value) ? $value : [],
            default => (string) ($value ?? ''),
        };
    }

    private function repeater(array $field, array $input): array
    {
        $field['fields'] = $this->normalizeAll($input['fields'] ?? []);
        $field['item_label'] = $field['fields'][0]['name'] ?? null;

        return $field;
    }

    private function select(array $field, array $input): array
    {
        $options = array_filter(
            (array) ($input['options'] ?? []),
            static fn ($label, $value) => is_scalar($label) && $value !== '',
            ARRAY_FILTER_USE_BOTH,
        );
        $field['options'] = $options ?: ['' => '—'];

        if (! array_key_exists((string) $field['default'], $field['options'])) {
            $field['default'] = (string) array_key_first($field['options']);
        }

        return $field;
    }
}
