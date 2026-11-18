<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Builder\DesignRenderer;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BuilderController extends Controller
{
    /** Live preview for the visual builder; data-block attributes let the editor map clicks to blocks. */
    public function preview(Request $request, DesignRenderer $renderer): Response
    {
        $design = json_decode((string) $request->input('design', '{}'), true);

        $page = $request->boolean('landing') ? ['form_action' => '#', 'title' => 'Preview'] : null;

        return response($renderer->render($design, editor: $request->boolean('editor', true), page: $page))
            ->header('Content-Security-Policy', "script-src 'none'");
    }

    public function media(): JsonResponse
    {
        return response()->json(Media::query()->latest()->limit(100)->get()->map(fn (Media $m) => [
            'id' => $m->id, 'url' => $m->url(), 'name' => $m->name,
        ]));
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120']]);
        $file = $request->file('file');
        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];
        $media = Media::query()->create([
            'path' => $file->store('media', 'public'),
            'name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
        ]);

        return response()->json(['id' => $media->id, 'url' => $media->url(), 'name' => $media->name], 201);
    }
}
