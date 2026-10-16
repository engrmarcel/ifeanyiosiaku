<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Domain\Content\Models\Article;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    use SavesArticles;

    protected static string $resource = ArticleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$this->prepareArticleData($data, null), 'author_id' => $this->articleActor()?->getKey()];
    }

    protected function afterCreate(): void
    {
        /** @var Article $article */
        $article = $this->getRecord();
        $this->recordArticleSave($article);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
