<?php

namespace App\Domain\Media\Models;

use App\Models\User;
use App\Policies\MediaAssetPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $ulid
 * @property string $disk
 * @property string $directory
 * @property string $filename
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt_text
 * @property string|null $title
 */
#[UsePolicy(MediaAssetPolicy::class)]
class MediaAsset extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return HasMany<MediaVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    /** @return HasMany<MediaUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function path(?string $filename = null): string
    {
        return $this->directory.'/'.($filename ?? $this->filename);
    }

    /** Public URL of the original, or of a named variant when it exists (falls back to the original). */
    public function url(?string $variant = null): string
    {
        $file = $this->filename;

        if ($variant !== null) {
            $match = $this->relationLoaded('variants')
                ? $this->variants->firstWhere('variant', $variant)
                : $this->variants()->where('variant', $variant)->first();
            $file = $match->filename ?? $file;
        }

        return Storage::disk($this->disk)->url($this->path($file));
    }

    /**
     * Shape used by the public API and block rendering. Only public, non-sensitive fields.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        $this->loadMissing('variants');

        return [
            'id' => $this->ulid,
            'url' => $this->url(),
            'mime' => $this->mime,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt_text ?? '',
            'title' => $this->title,
            'variants' => $this->variants
                ->sortBy('width')
                ->map(fn (MediaVariant $v) => [
                    'name' => $v->variant,
                    'url' => Storage::disk($this->disk)->url($this->path($v->filename)),
                    'width' => $v->width,
                    'height' => $v->height,
                    'mime' => $v->mime,
                ])
                ->values()
                ->all(),
        ];
    }
}
