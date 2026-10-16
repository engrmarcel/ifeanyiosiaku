<?php

namespace App\Domain\Content;

use App\Domain\Content\Markdown\MarkdownRenderer;
use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Tag;
use App\Domain\Media\Models\MediaAsset;

/** Public shapes of an article: a card for lists, and the full article for its own page. */
class ArticlePresenter
{
    public function __construct(private readonly MarkdownRenderer $markdown) {}

    /**
     * @return array<string, mixed>
     */
    public function card(Article $article): array
    {
        $article->loadMissing(['cover.variants', 'categories']);

        return [
            'slug' => $article->slug,
            'url' => $article->path(),
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'cover' => self::image($article->cover),
            'publishedAt' => $article->published_at?->toIso8601String(),
            'readingMinutes' => $article->reading_minutes,
            'categories' => $article->categories->map(fn (Category $c) => $c->toPublic())->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function full(Article $article, bool $draft = false): array
    {
        $article->loadMissing('tags');
        $seo = is_array($article->seo) ? $article->seo : [];
        $rendered = $this->markdown->render($article->body);
        $shareImage = filled($seo['og_media'] ?? null)
            ? MediaAsset::query()->with('variants')->whereKey($seo['og_media'])->first()
            : null;

        return [
            ...$this->card($article),
            'id' => $article->ulid,
            'draft' => $draft,
            'updatedAt' => $article->updated_at?->toIso8601String(),
            'tags' => $article->tags->map(fn (Tag $t) => $t->toPublic())->values()->all(),
            'segments' => $rendered['segments'],
            'toc' => $rendered['toc'],
            'readingMinutes' => $rendered['readingMinutes'],
            'seo' => [
                'title' => $seo['title'] ?? null,
                'description' => $seo['description'] ?? $article->excerpt,
                'noindex' => $draft || (bool) ($seo['noindex'] ?? false),
                'image' => self::image($shareImage) ?? self::image($article->cover),
            ],
            'related' => $draft ? [] : $this->related($article),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function image(?MediaAsset $media): ?array
    {
        return $media !== null && $media->isImage() ? $media->toPublicArray() : null;
    }

    /**
     * Up to three other published articles sharing a category, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function related(Article $article): array
    {
        $categoryIds = $article->categories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return [];
        }

        return array_values(Article::query()->published()
            ->whereKeyNot($article->getKey())
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->with(['cover.variants', 'categories'])
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Article $related) => $this->card($related))
            ->all());
    }
}
