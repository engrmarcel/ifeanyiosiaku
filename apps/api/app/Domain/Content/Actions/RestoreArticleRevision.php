<?php

namespace App\Domain\Content\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Content\ContentObserver;
use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Tag;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Brings back an earlier version of an article's content. Its status and publish time stay as
 * they are (restoring never unpublishes), and the restore itself becomes a new revision.
 */
class RestoreArticleRevision
{
    public function __construct(
        private readonly RecordArticleRevision $revisions,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Article $article, ContentRevision $revision, ?User $actor): Article
    {
        if ($revision->revisionable_type !== $article->getMorphClass() || (int) $revision->revisionable_id !== $article->getKey()) {
            throw ValidationException::withMessages(['revision' => 'This version belongs to different content.']);
        }

        $data = $revision->payload;
        $slug = (string) ($data['slug'] ?? $article->slug);

        if (Article::withTrashed()->where('slug', $slug)->whereKeyNot($article->getKey())->exists()) {
            throw ValidationException::withMessages(['revision' => "Another article now uses the address \"{$slug}\". Change that article first."]);
        }

        DB::transaction(function () use ($article, $data, $slug, $actor, $revision) {
            $cover = $data['cover_media_id'] ?? null;

            $article->fill([
                'title' => (string) ($data['title'] ?? $article->title),
                'slug' => $slug,
                'excerpt' => $data['excerpt'] ?? null,
                'body' => (string) ($data['body'] ?? ''),
                // A cover image deleted since then is simply left out.
                'cover_media_id' => $cover !== null && MediaAsset::query()->whereKey($cover)->exists() ? $cover : null,
                'seo' => $data['seo'] ?? null,
                'updated_by' => $actor?->getKey(),
            ])->save();

            $article->categories()->sync(Category::query()->whereIn('id', (array) ($data['category_ids'] ?? []))->pluck('id'));
            $article->tags()->sync(Tag::query()->whereIn('id', (array) ($data['tag_ids'] ?? []))->pluck('id'));

            $this->revisions->handle($article, $actor, "Restored version {$revision->number}");
        });

        $this->audit->record('article.revision_restored', $article, ['revision' => $revision->number], $actor);
        ContentObserver::refresh($article);

        return $article->refresh();
    }
}
