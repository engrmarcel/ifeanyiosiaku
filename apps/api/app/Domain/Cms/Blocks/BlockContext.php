<?php

namespace App\Domain\Cms\Blocks;

use App\Domain\Cms\Support\RichTextSanitizer;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Support\Collection;

/**
 * Shared services for turning stored block data into public output. Media referenced by all blocks
 * of a page is loaded in one query.
 */
class BlockContext
{
    /** @var Collection<string, MediaAsset> */
    private Collection $media;

    /**
     * @param  list<string>  $mediaIds
     */
    public function __construct(public readonly RichTextSanitizer $sanitizer, array $mediaIds = [])
    {
        $this->media = $mediaIds === []
            ? collect()
            : MediaAsset::query()->with('variants')->whereIn('ulid', $mediaIds)->get()->keyBy('ulid');
    }

    /** @return array<string, mixed>|null */
    public function media(?string $ulid): ?array
    {
        if ($ulid === null || $ulid === '') {
            return null;
        }

        $asset = $this->media->get($ulid);

        // Image slots only ever show images, even if a document's ID was stored by mistake.
        return $asset !== null && $asset->isImage() ? $asset->toPublicArray() : null;
    }

    public function html(?string $html): string
    {
        return $this->sanitizer->clean($html);
    }
}
