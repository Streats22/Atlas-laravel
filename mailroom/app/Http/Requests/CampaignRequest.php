<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Builder\DesignRenderer;
use App\Builder\EmailDesign;
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
            'html' => ['required_without:design', 'nullable', 'string', 'max:500000'],
            'design' => ['nullable', 'json', 'max:1000000'],
        ];
    }

    /**
     * Validated attributes; a builder design is normalised and its HTML rendered server-side,
     * so the stored html never contains anything the builder can't produce.
     *
     * @return array<string, mixed>
     */
    public function campaignData(): array
    {
        $data = $this->validated();
        if (! empty($data['design'])) {
            $design = app(EmailDesign::class)->normalize(json_decode($data['design'], true));
            $data['design'] = $design;
            $data['html'] = app(DesignRenderer::class)->render($design);
        } else {
            $data['design'] = null;
        }

        return $data;
    }
}
