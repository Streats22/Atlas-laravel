<?php

declare(strict_types=1);

namespace Atlas\Http\Controllers;

use Atlas\Blocks\DbBlock;
use Atlas\Blocks\FieldNormalizer;
use Atlas\Facades\Atlas;
use Atlas\Http\Requests\SaveBlockRequest;
use Atlas\Models\CustomBlock;
use Illuminate\Http\JsonResponse;

/** CRUD for blocks created in the editor's block builder. */
class BlockController
{
    public function __construct(private readonly FieldNormalizer $fields)
    {
    }

    public function store(SaveBlockRequest $request): JsonResponse
    {
        return $this->respond(CustomBlock::create($request->blockData($this->fields)));
    }

    public function update(SaveBlockRequest $request, CustomBlock $block): JsonResponse
    {
        $data = $request->blockData($this->fields);
        unset($data['type']); // the machine name never changes: pages reference it
        $block->update($data);

        return $this->respond($block->refresh());
    }

    public function destroy(CustomBlock $block): JsonResponse
    {
        abort_unless(config('atlas.custom_code'), 403, 'Custom code is disabled.');

        Atlas::blocks()->forget($block->type);
        $block->delete();

        return response()->json(['ok' => true]);
    }

    private function respond(CustomBlock $block): JsonResponse
    {
        Atlas::blocks()->register(new DbBlock($block));

        return response()->json([
            'block' => $block->toBuilder(),
            'definition' => Atlas::blocks()->get($block->type)->toDefinition(),
        ]);
    }
}
