<?php

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Stores an uploaded image in the media library (SECURITY_THREAT_MODEL T6).
 *
 * - Detects the real type from the file contents (never trusts the name or browser-reported type).
 * - Rejects oversized files and "decompression bomb" dimensions before decoding.
 * - Re-encodes the image, which removes metadata (camera details, GPS location) and any payload
 *   hidden in the file, and applies the camera's rotation.
 * - Generates responsive WebP variants for the website.
 */
class StoreMediaUpload
{
    public const MAX_BYTES = 15 * 1024 * 1024;

    public const MAX_DIMENSION = 8000;

    /** 25 megapixels, e.g. 6000 × 4000. Larger photos need too much memory to process safely. */
    public const MAX_PIXELS = 25_000_000;

    /** Highest memory limit this action may raise PHP to while processing one image. */
    private const MEMORY_CEILING = 1024 * 1024 * 1024;

    /** @var array<string, string> detected MIME => stored extension */
    public const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'png', // stored as a still PNG (first frame)
    ];

    /** @var array<string, int> variant name => target width */
    public const VARIANTS = ['sm' => 320, 'md' => 768, 'lg' => 1280, 'xl' => 1920];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{title?: string|null, alt_text?: string|null, description?: string|null}  $meta
     */
    public function handle(UploadedFile $file, ?User $actor, array $meta = []): MediaAsset
    {
        $source = $file->getRealPath();

        if ($source === false || ! is_file($source)) {
            throw ValidationException::withMessages(['file' => 'The upload could not be read.']);
        }

        if (filesize($source) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Images must be 15 MB or smaller.']);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($source);

        if (! array_key_exists($mime, self::ALLOWED)) {
            throw ValidationException::withMessages(['file' => 'Only JPEG, PNG, WebP or GIF images can be uploaded.']);
        }

        $info = @getimagesize($source);

        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw ValidationException::withMessages(['file' => 'This file is not a valid image.']);
        }

        if ($info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            throw ValidationException::withMessages(['file' => 'Images must be at most 8000 × 8000 pixels.']);
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['file' => 'Images must be at most 25 megapixels (for example 6000 × 4000).']);
        }

        $this->ensureMemoryFor($info[0], $info[1]);

        $checksum = hash_file('sha256', $source);
        $existing = MediaAsset::query()->where('checksum_sha256', $checksum)->first();

        if ($existing !== null) {
            return $existing;
        }

        $image = $this->decode($source, $mime);
        $image = $this->applyOrientation($image, $source, $mime);

        $extension = self::ALLOWED[$mime];
        $storedMime = $extension === 'png' ? 'image/png' : $mime;
        $ulid = strtolower((string) Str::ulid());
        $base = Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image', 60, '');
        $disk = Storage::disk('media');
        $written = [];

        try {
            return DB::transaction(function () use ($image, $extension, $storedMime, $ulid, $base, $disk, &$written, $file, $checksum, $actor, $meta) {
                $originalName = "{$base}.{$extension}";
                $written[] = "{$ulid}/{$originalName}";
                $disk->put("{$ulid}/{$originalName}", $this->encode($image, $extension, 90));

                $asset = MediaAsset::query()->create([
                    'disk' => 'media',
                    'directory' => $ulid,
                    'filename' => $originalName,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime' => $storedMime,
                    'size' => $disk->size("{$ulid}/{$originalName}"),
                    'width' => imagesx($image),
                    'height' => imagesy($image),
                    'title' => $meta['title'] ?? null,
                    'alt_text' => $meta['alt_text'] ?? null,
                    'description' => $meta['description'] ?? null,
                    'checksum_sha256' => $checksum,
                    'uploaded_by' => $actor?->getKey(),
                ]);

                foreach (self::VARIANTS as $name => $targetWidth) {
                    if (imagesx($image) <= $targetWidth) {
                        continue;
                    }

                    $resized = imagescale($image, $targetWidth, -1, IMG_BICUBIC);

                    if (! $resized instanceof GdImage) {
                        continue;
                    }

                    $variantName = "{$base}-{$name}.webp";
                    $written[] = "{$ulid}/{$variantName}";
                    $disk->put("{$ulid}/{$variantName}", $this->encode($resized, 'webp', 82));

                    $asset->variants()->create([
                        'variant' => $name,
                        'filename' => $variantName,
                        'mime' => 'image/webp',
                        'width' => imagesx($resized),
                        'height' => imagesy($resized),
                        'size' => $disk->size("{$ulid}/{$variantName}"),
                    ]);
                }

                $this->audit->record('media.uploaded', $asset, ['filename' => $originalName], $actor);

                return $asset->load('variants');
            });
        } catch (Throwable $e) {
            foreach ($written as $path) {
                $disk->delete($path);
            }
            $disk->deleteDirectory($ulid);

            throw $e;
        }
    }

    /**
     * A decoded image uses about 4 bytes per pixel, plus a rotated copy and the resized variants.
     * Raise PHP's memory limit for this request when needed (up to MEMORY_CEILING), or refuse
     * politely instead of crashing the request.
     */
    private function ensureMemoryFor(int $width, int $height): void
    {
        $needed = memory_get_usage(true) + (int) ($width * $height * 4 * 2.6) + 32 * 1024 * 1024;
        $limit = $this->bytes((string) ini_get('memory_limit'));

        if ($limit < 0 || $needed <= $limit) {
            return;
        }

        if ($needed > self::MEMORY_CEILING || @ini_set('memory_limit', (string) $needed) === false) {
            throw ValidationException::withMessages(['file' => 'This image is too large for the server to process. Please upload a smaller version.']);
        }
    }

    private function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function decode(string $source, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => @imagecreatefromwebp($source),
            'image/gif' => @imagecreatefromgif($source),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['file' => 'This image could not be processed.']);
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    private function applyOrientation(GdImage $image, string $source, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($source);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function encode(GdImage $image, string $extension, int $quality): string
    {
        ob_start();

        $ok = match ($extension) {
            'jpg' => imagejpeg($image, null, $quality),
            'png' => imagepng($image, null, 6),
            'webp' => imagewebp($image, null, $quality),
            default => false,
        };

        $bytes = (string) ob_get_clean();

        if (! $ok || $bytes === '') {
            throw ValidationException::withMessages(['file' => 'This image could not be saved.']);
        }

        return $bytes;
    }
}
