<?php

declare(strict_types=1);

namespace Atlas\Http\Requests;

use Atlas\Enums\PageStatus;
use Atlas\Enums\Spacing;
use Atlas\Enums\ThemeMode;
use Atlas\Models\Page;
use Atlas\Support\PageMeta;
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
        $hex = ['nullable', 'regex:/^#[0-9a-fA-F]{3,8}$/'];

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:[\-\/][a-z0-9]+)*$/',
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
        $reserved = trim((string) config('atlas.path'), '/');

        if ($reserved !== '' && ($value === $reserved || str_starts_with((string) $value, $reserved . '/'))) {
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
