<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FieldType;
use App\Models\Audience;
use App\Models\AudienceField;
use App\Services\EmailRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AudienceFieldController extends Controller
{
    public function index(Audience $audience): View
    {
        return view('audiences.fields', ['audience' => $audience, 'fields' => $audience->fields()->get()]);
    }

    public function store(Request $request, Audience $audience): RedirectResponse
    {
        $data = $this->validated($request, $audience);
        $data['key'] = $this->uniqueKey($audience, ($data['key'] ?? '') ?: $data['label']);
        $data['position'] = (int) $audience->fields()->max('position') + 1;
        $audience->fields()->create($data);

        return back()->with('status', "Field added. Use {{ {$data['key']} }} in emails.");
    }

    public function update(Request $request, Audience $audience, AudienceField $field): RedirectResponse
    {
        $data = $this->validated($request, $audience);
        unset($data['key'], $data['type']); // Changing these would orphan stored values and merge tags.
        $field->update($data);

        return back()->with('status', 'Field saved.');
    }

    public function destroy(Audience $audience, AudienceField $field): RedirectResponse
    {
        $field->delete();

        return back()->with('status', 'Field removed. Existing values stay on contacts until they are edited.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Audience $audience): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'key' => ['nullable', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::notIn(EmailRenderer::MERGE_TAGS)],
            'type' => ['required', Rule::enum(FieldType::class)],
            'options' => ['nullable', 'required_if:type,dropdown', 'string', 'max:2000'],
            'required' => ['boolean'],
            'show_on_form' => ['boolean'],
        ]);
        $data['required'] = $request->boolean('required');
        $data['show_on_form'] = $request->boolean('show_on_form');
        $data['options'] = $data['type'] === FieldType::Dropdown->value
            ? array_values(array_unique(array_filter(array_map(fn ($o) => mb_substr(trim($o), 0, 100), explode("\n", (string) $data['options'])))))
            : null;

        return $data;
    }

    private function uniqueKey(Audience $audience, string $source): string
    {
        $base = Str::of($source)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(30, '')->value();
        $base = preg_match('/^[a-z]/', $base) ? $base : 'field_'.$base;
        $key = in_array($base, EmailRenderer::MERGE_TAGS, true) ? $base.'_2' : $base;
        for ($i = 2; $audience->fields()->where('key', $key)->exists(); $i++) {
            $key = $base.'_'.$i;
        }

        return $key;
    }
}
