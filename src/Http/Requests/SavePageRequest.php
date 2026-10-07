<?php

declare(strict_types=1);

namespace Atlas\Http\Requests;

use Atlas\Enums\PageStatus;
use Atlas\Enums\Spacing;
use Atlas\Enums\ThemeMode;
use Atlas\Models\Page;
use Atlas\Support\PageMeta;
use Atlas\Support\SlugPolicy;
use Atlas\Support\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the editor's "save page" payload. */
class SavePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // access is enforced by the useAtlas gate middleware
    }

    public function rules(): array
    {
        /** @var Page $page */
        $page = $this->route('page');
        $hex = ['nullable', 'regex:' . Theme::HEX_COLOR];

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:190', 'regex:' . SlugPolicy::PATTERN,
                Rule::unique($page->getTable(), 'slug')->ignore($page->getKey()),
                $this->notReserved(...),
            ],
            'status' => ['required', Rule::enum(PageStatus::class)],
            'content' => ['present', 'array'],
            'css' => ['nullable', 'string'],
            'js' => ['nullable', 'string'],
            'head' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
            'meta.description' => ['nullable', 'string', 'max:500'],
            'meta.theme' => ['nullable', Rule::enum(ThemeMode::class)],
            'meta.theme_toggle' => ['nullable', 'boolean'],
            'meta.accent' => $hex,
            'meta.accent_dark' => $hex,
            'meta.font' => ['nullable', Rule::in(array_keys(Theme::FONTS))],
            'meta.heading_font' => ['nullable', Rule::in(['same', ...array_keys(Theme::FONTS)])],
            'meta.spacing' => ['nullable', Rule::enum(Spacing::class)],
            'meta.og_image' => ['nullable', 'string', 'max:500'],
            'meta.titles' => ['nullable', 'array'],
            'meta.titles.*' => ['nullable', 'string', 'max:255'],
            'meta.descriptions' => ['nullable', 'array'],
            'meta.descriptions.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** The slug must not collide with the editor's own URL space. */
    private function notReserved(string $attribute, mixed $value, \Closure $fail): void
    {
        if (SlugPolicy::isReserved((string) $value)) {
            $fail('That slug is reserved for the Atlas editor.');
        }
    }

    public function status(): PageStatus
    {
        return PageStatus::from($this->validated('status'));
    }

    public function meta(): PageMeta
    {
        return PageMeta::sanitize((array) $this->validated('meta', []));
    }
}
