<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\ContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Snapshots an article (with its categories and tags) after each save, keeping the latest 50. */
class RecordArticleRevision
{
    public const KEEP = 50;

    public function handle(Article $article, ?User $actor, ?string $note = null): ContentRevision
    {
        return DB::transaction(function () use ($article, $actor, $note) {
            $last = ContentRevision::query()
                ->where('revisionable_type', $article->getMorphClass())
                ->where('revisionable_id', $article->getKey())
                ->lockForUpdate()
                ->max('number');

            $revision = $article->revisions()->create([
                'number' => ((int) $last) + 1,
                'payload' => self::payload($article),
                'note' => $note,
                'created_by' => $actor?->getKey(),
            ]);

            $stale = $article->revisions()->skip(self::KEEP)->take(PHP_INT_MAX)->pluck('id');
            if ($stale->isNotEmpty()) {
                ContentRevision::query()->whereIn('id', $stale)->delete();
            }

            return $revision;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(Article $article): array
    {
        return [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'cover_media_id' => $article->cover_media_id,
            'seo' => $article->seo,
            'category_ids' => $article->categories()->pluck('categories.id')->all(),
            'tag_ids' => $article->tags()->pluck('tags.id')->all(),
        ];
    }
}
