<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Domain\Content\Actions\RecordArticleRevision;
use App\Domain\Content\ContentObserver;
use App\Domain\Content\Models\Article;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Shared save rules for the create and edit screens. */
trait SavesArticles
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareArticleData(array $data, ?Article $record): array
    {
        $status = $data['status'] ?? $record->status ?? Article::STATUS_DRAFT;
        $wasPublished = $record?->status === Article::STATUS_PUBLISHED;

        // The status field is disabled without the publish permission; this is the server-side check.
        if ($status === Article::STATUS_PUBLISHED && ! $wasPublished && ! ArticleResource::canPublish()) {
            throw ValidationException::withMessages(['data.status' => 'You do not have permission to publish articles.']);
        }

        if ($status === Article::STATUS_PUBLISHED && blank($data['published_at'] ?? $record?->published_at)) {
            $data['published_at'] = now();
        }

        $data['updated_by'] = $this->articleActor()?->getKey();

        return $data;
    }

    /** Snapshot after categories and tags are saved, then refresh the website. */
    protected function recordArticleSave(Article $article): void
    {
        app(RecordArticleRevision::class)->handle($article, $this->articleActor());
        ContentObserver::refresh($article);
    }

    protected function articleActor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
