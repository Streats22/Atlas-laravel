<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AudienceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'from_name' => ['required', 'string', 'max:120'],
            'from_email' => ['required', 'email', 'max:190'],
            'double_opt_in' => ['boolean'],
            'gdpr_enabled' => ['boolean'],
            'gdpr_text' => ['nullable', 'required_if:gdpr_enabled,1', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['double_opt_in' => $this->boolean('double_opt_in'), 'gdpr_enabled' => $this->boolean('gdpr_enabled')]);
    }
}
