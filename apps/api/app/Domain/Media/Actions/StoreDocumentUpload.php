<?php

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Stores a free, public PDF (for example a publication or a download) in the media library.
 *
 * - Detects the real type from the contents and checks the PDF signature.
 * - Refuses PDFs with active content (scripts, launch actions, embedded files or rich media), which
 *   PDF readers could run on a visitor's computer.
 * - Always served as a download, never displayed inside the website.
 *
 * Unlike images, PDFs are stored unchanged, so document properties (author, software) remain; the
 * admin form says so. Paid files use the private disk added with the store (Milestone 3).
 */
class StoreDocumentUpload
{
    public const MAX_BYTES = 30 * 1024 * 1024;

    public const ALLOWED = ['application/pdf' => 'pdf'];

    /**
     * PDF name objects that run code, start programs or carry embedded content. Ordinary "open at
     * page 1" actions (/OpenAction) are allowed: they are harmless unless they trigger one of these.
     */
    private const ACTIVE_CONTENT = ['/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/EmbeddedFiles', '/RichMedia', '/XFA'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{title?: string|null, description?: string|null}  $meta
     */
    public function handle(UploadedFile $file, ?User $actor, array $meta = []): MediaAsset
    {
        $source = $file->getRealPath();

        if ($source === false || ! is_file($source)) {
            throw ValidationException::withMessages(['file' => 'The upload could not be read.']);
        }

        if (filesize($source) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Documents must be 30 MB or smaller.']);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        $handle = fopen($source, 'rb');
        $signature = $handle !== false ? (string) fread($handle, 5) : '';
        if ($handle !== false) {
            fclose($handle);
        }

        if (! array_key_exists($mime, self::ALLOWED) || $signature !== '%PDF-') {
            throw ValidationException::withMessages(['file' => 'Only PDF documents can be uploaded.']);
        }

        if ($this->hasActiveContent($source)) {
            throw ValidationException::withMessages(['file' => 'This PDF contains scripts, automatic actions or embedded files, which are not allowed for safety. Export it again as a plain PDF (for example "Save as PDF" or "Print to PDF").']);
        }

        $checksum = hash_file('sha256', $source);
        $existing = MediaAsset::query()->where('checksum_sha256', $checksum)->first();

        if ($existing !== null) {
            return $existing;
        }

        $ulid = strtolower((string) Str::ulid());
        $base = Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'document', 60, '');
        $filename = "{$base}.pdf";
        $disk = Storage::disk('media');

        try {
            return DB::transaction(function () use ($disk, $ulid, $filename, $source, $file, $checksum, $actor, $meta) {
                $stream = fopen($source, 'rb');
                if ($stream === false) {
                    throw ValidationException::withMessages(['file' => 'The upload could not be read.']);
                }
                $disk->writeStream("{$ulid}/{$filename}", $stream);
                fclose($stream);

                $asset = MediaAsset::query()->create([
                    'disk' => 'media',
                    'directory' => $ulid,
                    'filename' => $filename,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime' => 'application/pdf',
                    'size' => $disk->size("{$ulid}/{$filename}"),
                    'title' => $meta['title'] ?? null,
                    'description' => $meta['description'] ?? null,
                    'checksum_sha256' => $checksum,
                    'uploaded_by' => $actor?->getKey(),
                ]);

                $this->audit->record('media.uploaded', $asset, ['filename' => $filename], $actor);

                return $asset;
            });
        } catch (Throwable $e) {
            $disk->deleteDirectory($ulid);

            throw $e;
        }
    }

    /**
     * Looks for active-content names anywhere in the file, including inside compressed object
     * streams. A name counts only when followed by a delimiter, so "/JSON" does not match "/JS".
     */
    private function hasActiveContent(string $path): bool
    {
        $content = (string) file_get_contents($path);
        $haystacks = [$content];

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $content, $streams)) {
            foreach ($streams[1] as $stream) {
                $inflated = @gzuncompress($stream);
                if ($inflated === false) {
                    $inflated = @gzinflate($stream);
                }
                if (is_string($inflated)) {
                    $haystacks[] = $inflated;
                }
            }
        }

        $names = implode('|', array_map(fn (string $n) => preg_quote($n, '#'), self::ACTIVE_CONTENT));

        foreach ($haystacks as $haystack) {
            // PDF names may hide letters as #xx hex escapes (e.g. /J#61vaScript).
            $decoded = (string) preg_replace_callback('/#([0-9a-fA-F]{2})/', fn ($m) => chr((int) hexdec($m[1])), $haystack);

            if (preg_match('#(?:'.$names.')(?=[\s/<>\[\]()%]|$)#', $decoded)) {
                return true;
            }
        }

        return false;
    }
}
