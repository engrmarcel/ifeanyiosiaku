<?php

namespace App\Domain\Content;

use App\Domain\Content\Models\Publication;
use App\Domain\Content\Models\PublicationVersion;
use Illuminate\Support\Str;

/** Public shapes of a publication and its released versions. */
class PublicationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function card(Publication $publication): array
    {
        $publication->loadMissing(['cover.variants', 'versions']);
        $latest = $publication->versions->first();

        return [
            'slug' => $publication->slug,
            'url' => $publication->path(),
            'title' => $publication->title,
            'type' => $publication->type,
            'typeLabel' => Publication::TYPES[$publication->type] ?? 'Publication',
            'summary' => $publication->abstract !== null ? Str::limit(trim($publication->abstract), 240) : null,
            'authors' => $publication->authors ?? [],
            'cover' => ArticlePresenter::image($publication->cover),
            'publishedAt' => $publication->published_at?->toIso8601String(),
            'latestVersion' => $latest?->version_label,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function full(Publication $publication): array
    {
        $publication->loadMissing('versions.file');
        $seo = is_array($publication->seo) ? $publication->seo : [];

        return [
            ...$this->card($publication),
            'id' => $publication->ulid,
            'abstract' => $publication->abstract,
            'doi' => $publication->doi,
            'isbn' => $publication->isbn,
            'updatedAt' => $publication->updated_at?->toIso8601String(),
            'versions' => $publication->versions->map(fn (PublicationVersion $v) => [
                'label' => $v->version_label,
                'changelog' => $v->changelog,
                'releasedAt' => $v->released_at->toDateString(),
                'file' => $v->file !== null && $v->file->mime === 'application/pdf'
                    ? ['url' => $v->file->url(), 'size' => $v->file->size, 'name' => $v->file->title ?: $v->file->filename]
                    : null,
                'externalUrl' => $v->external_url,
            ])->values()->all(),
            'seo' => [
                'title' => $seo['title'] ?? null,
                'description' => $seo['description'] ?? $this->card($publication)['summary'],
                'noindex' => (bool) ($seo['noindex'] ?? false),
                'image' => ArticlePresenter::image($publication->cover),
            ],
        ];
    }
}
