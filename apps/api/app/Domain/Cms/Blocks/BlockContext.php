<?php

namespace App\Domain\Cms\Blocks;

use App\Domain\Cms\Support\EmbedToken;
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

    /** Position of the block being rendered within the stored list. */
    public int $index = 0;

    /**
     * @param  list<string>  $mediaIds
     * @param  array{page: string, draft: bool}|null  $embedScope  page the blocks belong to, for sandboxed embeds
     */
    public function __construct(
        public readonly RichTextSanitizer $sanitizer,
        array $mediaIds = [],
        private readonly ?array $embedScope = null,
    ) {
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

    /**
     * A downloadable PDF from the media library.
     *
     * @return array{url: string, name: string, size: int}|null
     */
    public function document(?string $ulid): ?array
    {
        $asset = $ulid !== null && $ulid !== '' ? $this->media->get($ulid) : null;

        return $asset !== null && $asset->mime === 'application/pdf'
            ? ['url' => $asset->url(), 'name' => $asset->title ?: $asset->filename, 'size' => $asset->size]
            : null;
    }

    /** Signed address of a sandboxed embed for the current block, or null outside a page. */
    public function embedUrl(string $content): ?string
    {
        if ($this->embedScope === null) {
            return null;
        }

        $token = app(EmbedToken::class)->issue($this->embedScope['page'], $this->embedScope['draft'], $this->index, $content);

        return '/api/v1/embeds/'.$token;
    }

    public function html(?string $html): string
    {
        return $this->sanitizer->clean($html);
    }
}
