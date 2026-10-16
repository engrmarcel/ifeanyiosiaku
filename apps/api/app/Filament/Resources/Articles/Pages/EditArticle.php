<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cms\PreviewToken;
use App\Domain\Content\Models\Article;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/** Saving a published article updates the live site immediately; drafts can be previewed first. */
class EditArticle extends EditRecord
{
    use SavesArticles;

    protected static string $resource = ArticleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Article $article */
        $article = $this->getRecord();

        return $this->prepareArticleData($data, $article);
    }

    protected function afterSave(): void
    {
        /** @var Article $article */
        $article = $this->getRecord();
        $this->recordArticleSave($article);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        /** @var Article $article */
        $article = $this->getRecord();

        return $article->isLive() ? 'Saved — the live article is updated' : 'Saved (not visible to visitors yet)';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (Article $record) => config('platform.frontend_url').'/preview/article?token='.app(PreviewToken::class)->issueForArticle($record), shouldOpenInNewTab: true)
                ->tooltip('Opens the saved version on the website. Save first to include your latest changes.'),
            ActionGroup::make([
                Action::make('view_live')
                    ->label('Open live article')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (Article $record) => $record->isLive() && ! $record->trashed())
                    ->url(fn (Article $record) => config('platform.frontend_url').$record->path(), shouldOpenInNewTab: true),
                DeleteAction::make()
                    ->label('Move to trash')
                    ->after(fn (Article $record) => app(AuditLogger::class)->record('article.deleted', $record, ['slug' => $record->slug], $this->articleActor())),
                RestoreAction::make(),
            ]),
        ];
    }
}
