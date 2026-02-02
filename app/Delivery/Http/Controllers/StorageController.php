<?php

namespace App\Delivery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\Glide\ServerFactory;

class StorageController extends Controller
{
    public function intercept(Request $request, $path)
    {
        $server = ServerFactory::create([
            'source' => storage_path('app/public'),
            'cache'  => storage_path('app/glide_cache'),
        ]);

        $publicPath = storage_path("app/public/{$path}");

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $mime = Storage::disk('public')->mimeType($path);
        $ext = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));

        if ($mime === 'image/svg+xml' || $ext === 'svg') {
            return response()->file($publicPath);
        }

        if (! str_starts_with($mime, 'image/')) {
            return response()->file($publicPath);
        }

        $params = $request->only(['w', 'h']);

        if (empty($params)) {
            return response()->file(storage_path("app/public/$path"));
        }

        try {
            $cachePath = $server->makeImage($path, $params);

            return response()->file(storage_path("app/glide_cache/$cachePath"), [
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        } catch (\Exception $e) {
            abort(404);
        }
    }
}
