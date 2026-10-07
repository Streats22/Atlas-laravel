<?php

declare(strict_types=1);

namespace Atlas\Http\Controllers;

class AssetController
{
    public function show(string $file)
    {
        $path = __DIR__ . '/../../../resources/dist/' . $file;
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => str_ends_with($file, '.css') ? 'text/css; charset=utf-8' : 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
