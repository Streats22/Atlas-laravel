<?php

namespace Atlas\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp,avif', 'max:'.config('atlas.uploads.max_kb', 5120)],
        ]);

        $disk = config('atlas.uploads.disk', 'public');
        $path = $request->file('file')->store(config('atlas.uploads.directory', 'atlas'), $disk);

        return response()->json(['url' => Storage::disk($disk)->url($path)]);
    }
}
