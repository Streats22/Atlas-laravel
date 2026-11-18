<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function rules(): array
    {
        $audienceId = (int) $this->input('audience_id');

        return [
            'name' => ['required', 'string', 'max:150'],
            'audience_id' => ['required', 'exists:audiences,id'],
            'segment_id' => ['nullable', Rule::exists('segments', 'id')->where('audience_id', $audienceId)],
            'tag_id' => ['nullable', Rule::exists('tags', 'id')->where('audience_id', $audienceId)],
            'subject' => ['required', 'string', 'max:190'],
            'preview_text' => ['nullable', 'string', 'max:190'],
            'from_name' => ['required', 'string', 'max:120'],
            'from_email' => ['required', 'email', 'max:190'],
            'html' => ['required', 'string', 'max:500000'],
        ];
    }
}
