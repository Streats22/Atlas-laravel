<?php

namespace Atlas\Http\Controllers;

use Atlas\Blocks\DbBlock;
use Atlas\Facades\Atlas;
use Atlas\Models\CustomBlock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CRUD for blocks created in the editor's block builder. */
class BlockController
{
    private const TYPES = ['text', 'textarea', 'number', 'select', 'color', 'checkbox', 'image', 'code', 'repeater'];

    private const SUB_TYPES = ['text', 'textarea', 'number', 'select', 'color', 'checkbox', 'image'];

    public function store(Request $request)
    {
        abort_unless(config('atlas.custom_code'), 403, 'Custom code is disabled.');

        $data = $this->validated($request);
        $block = CustomBlock::create($data);
        Atlas::blocks()->register(new DbBlock($block));

        return $this->respond($block);
    }

    public function update(Request $request, CustomBlock $block)
    {
        abort_unless(config('atlas.custom_code'), 403, 'Custom code is disabled.');

        $data = $this->validated($request, $block);
        unset($data['type']); // the machine name never changes: pages reference it
        $block->update($data);
        Atlas::blocks()->register(new DbBlock($block->refresh()));

        return $this->respond($block);
    }

    public function destroy(CustomBlock $block)
    {
        abort_unless(config('atlas.custom_code'), 403, 'Custom code is disabled.');

        Atlas::blocks()->forget($block->type);
        $block->delete();

        return response()->json(['ok' => true]);
    }

    protected function respond(CustomBlock $block)
    {
        return response()->json([
            'block' => $block->toBuilder(),
            'definition' => Atlas::blocks()->get($block->type)->toDefinition(),
        ]);
    }

    protected function validated(Request $request, ?CustomBlock $existing = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'type' => [
                $existing ? 'sometimes' : 'required', 'string', 'regex:/^[a-z][a-z0-9-]{1,48}$/',
                Rule::unique('atlas_blocks', 'type')->ignore($existing?->id),
                function ($attr, $value, $fail) use ($existing) {
                    if (! $existing && Atlas::blocks()->has($value)) {
                        $fail(__('atlas::ui.block_exists'));
                    }
                },
            ],
            'category' => ['nullable', 'string', 'max:40'],
            'icon' => ['nullable', 'string', 'max:8'],
            'container' => ['boolean'],
            'fields' => ['array', 'max:40'],
            'fields.*.name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,40}$/', 'distinct'],
            'fields.*.label' => ['nullable', 'string', 'max:80'],
            'fields.*.type' => ['required', Rule::in(self::TYPES)],
            'fields.*.default' => ['nullable'],
            'fields.*.translatable' => ['boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.language' => ['nullable', 'string', 'max:12'],
            'fields.*.fields' => ['nullable', 'array', 'max:12'],
            'fields.*.fields.*.name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,40}$/'],
            'fields.*.fields.*.label' => ['nullable', 'string', 'max:80'],
            'fields.*.fields.*.type' => ['required', Rule::in(self::SUB_TYPES)],
            'fields.*.fields.*.default' => ['nullable'],
            'fields.*.fields.*.translatable' => ['boolean'],
            'fields.*.fields.*.options' => ['nullable', 'array'],
            'html' => ['nullable', 'string'],
            'css' => ['nullable', 'string'],
            'js' => ['nullable', 'string'],
        ]);

        $data['fields'] = array_map(fn ($f) => $this->field($f), $data['fields'] ?? []);
        $data['category'] = ($data['category'] ?? null) ?: 'Custom';
        $data['container'] = (bool) ($data['container'] ?? false);

        return $data;
    }

    protected function field(array $f): array
    {
        $type = $f['type'];
        $out = [
            'type' => $type,
            'name' => $f['name'],
            'label' => ($f['label'] ?? null) ?: ucfirst(str_replace('_', ' ', $f['name'])),
            'default' => $f['default'] ?? null,
        ];

        if ($type === 'number') {
            $out['default'] = is_numeric($out['default']) ? $out['default'] + 0 : 0;
        } elseif ($type === 'checkbox') {
            $out['default'] = filter_var($out['default'], FILTER_VALIDATE_BOOLEAN);
        } elseif ($type === 'repeater') {
            $out['default'] = is_array($out['default']) ? $out['default'] : [];
            $out['fields'] = array_map(fn ($s) => $this->field($s), $f['fields'] ?? []);
            $out['item_label'] = $out['fields'][0]['name'] ?? null;
        } else {
            $out['default'] = (string) ($out['default'] ?? '');
        }

        if ($type === 'select') {
            $options = array_filter((array) ($f['options'] ?? []), fn ($v, $k) => is_scalar($v) && $k !== '', ARRAY_FILTER_USE_BOTH);
            $out['options'] = $options ?: ['' => '—'];
            if (! array_key_exists((string) $out['default'], $out['options'])) {
                $out['default'] = (string) array_key_first($out['options']);
            }
        }
        if ($type === 'code') {
            $out['language'] = $f['language'] ?? 'html';
        }
        if (! empty($f['translatable']) && in_array($type, ['text', 'textarea'], true)) {
            $out['translatable'] = true;
        }

        return $out;
    }
}
