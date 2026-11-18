<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\SegmentQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SegmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'match' => ['required', 'in:all,any'],
            'conditions' => ['required', 'array', 'min:1', 'max:20'],
            'conditions.*.field' => ['required', 'string'],
            'conditions.*.operator' => ['required', 'string'],
            'conditions.*.value' => ['nullable', 'string', 'max:190'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Drop the empty template row the form always renders.
        $rows = array_values(array_filter(
            (array) $this->input('conditions', []),
            fn ($c) => is_array($c) && ($c['field'] ?? '') !== '',
        ));
        $this->merge(['conditions' => $rows]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $query = app(SegmentQuery::class);
            foreach ((array) $this->input('conditions', []) as $i => $condition) {
                $customKey = is_array($condition) && str_starts_with((string) ($condition['field'] ?? ''), 'field:') ? substr($condition['field'], 6) : null;
                $unknownField = $customKey !== null && ! $this->route('audience')->fields()->where('key', $customKey)->exists();
                if (! is_array($condition) || ! $query->isValid($condition) || $unknownField) {
                    $validator->errors()->add("conditions.{$i}", 'This condition is not valid.');
                }
            }
        }];
    }

    /** @return list<array{field:string, operator:string, value:string}> */
    public function conditions(): array
    {
        return array_map(fn (array $c) => [
            'field' => $c['field'],
            'operator' => $c['operator'],
            'value' => (string) ($c['value'] ?? ''),
        ], $this->validated('conditions'));
    }
}
