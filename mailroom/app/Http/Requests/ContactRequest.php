<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContactStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function rules(): array
    {
        $audience = $this->route('audience');
        $contact = $this->route('contact');

        return [
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('contacts', 'email')->where('audience_id', $audience->id)->ignore($contact?->id),
            ],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(ContactStatus::class)],
            'tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    /** @return list<string> */
    public function tagNames(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $t) => mb_substr(trim($t), 0, 60),
            explode(',', (string) $this->input('tags')),
        ))));
    }
}
