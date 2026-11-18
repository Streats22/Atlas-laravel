<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Audience;
use App\Models\AudienceField;
use Illuminate\Support\Facades\Validator;

/** Validation and cleaning of custom field values against an audience's field definitions. */
final class ContactFields
{
    /**
     * @param  'all'|'form'  $scope
     * @return array<string, list<mixed>>
     */
    public function rules(Audience $audience, string $scope = 'all', bool $enforceRequired = true): array
    {
        $rules = [];
        foreach ($audience->fields as $field) {
            if ($scope === 'form' && ! $field->show_on_form) {
                continue;
            }
            $rules['fields.'.$field->key] = $field->rules($enforceRequired);
        }

        return $rules;
    }

    /**
     * Keep only known keys, drop empty values, merge over existing values.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $existing
     * @return array<string, string>|null
     */
    public function clean(Audience $audience, array $input, ?array $existing = null): ?array
    {
        $values = $existing ?? [];
        foreach ($audience->fields as $field) {
            if (! array_key_exists($field->key, $input)) {
                continue;
            }
            $value = trim((string) $input[$field->key]);
            if ($value === '') {
                unset($values[$field->key]);
            } else {
                $values[$field->key] = $value;
            }
        }

        return $values === [] ? null : $values;
    }

    /** Validate a single raw value (used by CSV import); null when invalid. */
    public function valid(AudienceField $field, string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return Validator::make(['v' => $value], ['v' => $field->rules(false)])->passes() ? $value : null;
    }
}
