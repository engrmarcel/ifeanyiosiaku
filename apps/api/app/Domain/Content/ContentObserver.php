<?php

namespace App\Domain\Content;

use App\Domain\Cms\AutomaticRedirects;
use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\LivePaths;
use App\Domain\Content\Markdown\MarkdownRenderer;
use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Publication;
use App\Domain\Content\Models\PublicationVersion;
use App\Domain\Content\Models\Tag;
use App\Domain\Media\MediaUsageRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps the website in step with articles, publications and their categories and tags: refreshes
 * the affected addresses (and pages showing "latest articles"), records media in use, and
 * redirects the old address when live content moves.
 */
class ContentObserver
{
    /** @var array<class-string<Model>, string> */
    private const MEDIA_FIELDS = [
        Article::class => 'cover_media_id',
        Publication::class => 'cover_media_id',
        PublicationVersion::class => 'file_media_id',
    ];

    public function __construct(private readonly MediaUsageRecorder $usages) {}

    public function saving(Model $model): void
    {
        if ($model instanceof Article && $model->isDirty('body')) {
            $model->reading_minutes = MarkdownRenderer::readingMinutes($model->body);
        }
    }

    public function saved(Model $model): void
    {
        if (isset(self::MEDIA_FIELDS[$model::class])) {
            $field = self::MEDIA_FIELDS[$model::class];
            $this->usages->sync($model, $field, array_filter([(int) $model->getAttribute($field)]));
        }

        if (($model instanceof Article || $model instanceof Publication) && $model->wasChanged('slug') && $this->wasLive($model)) {
            $prefix = $model instanceof Article ? '/articles/' : '/publications/';
            $userId = Auth::id();
            AutomaticRedirects::moved($prefix.$model->getOriginal('slug'), $model->path(), is_int($userId) ? $userId : null);
        }

        self::refresh($model);
    }

    public function deleted(Model $model): void
    {
        self::refresh($model);
    }

    public function restored(Model $model): void
    {
        self::refresh($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->usages->forget($model);
    }

    /** Asks the website to refresh every address that shows this content. */
    public static function refresh(Model $model): void
    {
        $paths = LivePaths::all(); // pages may show a "latest articles" section

        if ($model instanceof Article) {
            $paths[] = '/articles';
            $paths[] = $model->path();
            if ($model->wasChanged('slug') && is_string($model->getOriginal('slug'))) {
                $paths[] = '/articles/'.$model->getOriginal('slug');
            }
        } elseif ($model instanceof Publication || $model instanceof PublicationVersion) {
            $publication = $model instanceof PublicationVersion ? $model->publication : $model;
            $paths[] = '/publications';
            if ($publication !== null) {
                $paths[] = $publication->path();
                if ($publication->wasChanged('slug') && is_string($publication->getOriginal('slug'))) {
                    $paths[] = '/publications/'.$publication->getOriginal('slug');
                }
            }
        } elseif ($model instanceof Category || $model instanceof Tag) {
            $paths[] = '/articles';
            foreach (Article::query()->published()->pluck('slug') as $slug) {
                $paths[] = '/articles/'.$slug;
            }
        }

        PageContentChanged::dispatch(array_values(array_unique($paths)));
    }

    /** Whether the content was publicly visible before this save. */
    private function wasLive(Article|Publication $model): bool
    {
        $publishedAt = $model->getOriginal('published_at');

        return $model->getOriginal('status') === 'published'
            && $publishedAt instanceof Carbon
            && $publishedAt->lte(now());
    }
}
