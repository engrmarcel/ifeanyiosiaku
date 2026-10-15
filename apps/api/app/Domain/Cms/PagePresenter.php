<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Blocks\BlockRegistry;
use App\Domain\Cms\Models\Page;
use App\Domain\Media\Models\MediaAsset;

/** Builds the public JSON for a page from a revision snapshot or the working draft. */
class PagePresenter
{
    public function __construct(private readonly BlockRegistry $blocks) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Page $page, bool $draft = false): array
    {
        $source = $draft ? $page : $page->publishedRevision;
        $title = $source->title ?? $page->title;
        $slug = $source->slug ?? $page->slug;
        $seo = is_array($source?->seo) ? $source->seo : [];
        $ogMedia = filled($seo['og_media'] ?? null)
            ? MediaAsset::query()->with('variants')->where('ulid', $seo['og_media'])->first()
            : null;

        return [
            'id' => $page->ulid,
            'title' => $title,
            'slug' => $slug,
            'path' => $page->is_homepage ? '/' : '/'.$slug,
            'isHomepage' => $page->is_homepage,
            'template' => $page->template,
            'draft' => $draft,
            'publishedAt' => $page->published_at?->toIso8601String(),
            'updatedAt' => ($draft ? $page->updated_at : $page->publishedRevision?->created_at)?->toIso8601String(),
            'seo' => [
                'title' => $seo['title'] ?? null,
                'description' => $seo['description'] ?? null,
                'canonicalUrl' => $seo['canonical_url'] ?? null,
                'noindex' => $draft || (bool) ($seo['noindex'] ?? false),
                'image' => $ogMedia?->toPublicArray(),
            ],
            'blocks' => $this->blocks->toPublic($source->blocks ?? []),
        ];
    }
}
