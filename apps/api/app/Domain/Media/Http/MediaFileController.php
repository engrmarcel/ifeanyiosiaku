<?php

namespace App\Domain\Media\Http;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves public media library files for local development and simple hosting. In production the
 * web server serves storage/app/media directly at /media (faster, no PHP involved).
 */
class MediaFileController
{
    public function __invoke(string $path): BinaryFileResponse
    {
        abort_if(str_contains($path, '..') || str_contains($path, '\\'), 404);

        $disk = Storage::disk('media');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            // Even if a file were crafted to look like HTML, it could not run scripts.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
