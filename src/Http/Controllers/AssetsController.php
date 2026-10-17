<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Routing\Controller;
use IsoDoss\Meridian\Support\Assets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetsController extends Controller
{
    /**
     * Serve a compiled asset of the Inertia interface. File names contain a
     * content hash, so they can be cached forever.
     */
    public function show(string $path): BinaryFileResponse
    {
        $file = Assets::path($path);

        abort_if($file === null, 404);

        return response()->file($file, [
            'Content-Type' => Assets::contentType($file),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
