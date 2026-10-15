<?php

namespace App\Domain\Portfolio;

use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\LivePaths;
use App\Domain\Cms\Support\RichTextSanitizer;
use App\Domain\Media\MediaUsageRecorder;
use App\Domain\Portfolio\Models\Certification;
use App\Domain\Portfolio\Models\Project;
use App\Domain\Portfolio\Models\Service;
use App\Domain\Portfolio\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;

/**
 * Portfolio content (projects, skills, services, ...) appears inside CMS pages through data-driven
 * blocks, so any change refreshes every published page, plus the project's own case-study page.
 */
class PortfolioObserver
{
    /** Media foreign keys per model, recorded as usages so in-use files cannot be deleted. */
    private const MEDIA_FIELDS = [
        Project::class => 'cover_media_id',
        Certification::class => 'media_id',
        Testimonial::class => 'avatar_media_id',
    ];

    public function __construct(private readonly MediaUsageRecorder $usages) {}

    public function saving(Model $model): void
    {
        // Rich text is sanitised when stored (and again when served).
        if (($model instanceof Project || $model instanceof Service) && $model->isDirty('body')) {
            $model->setAttribute('body', app(RichTextSanitizer::class)->clean($model->getAttribute('body')));
        }
    }

    public function saved(Model $model): void
    {
        if (isset(self::MEDIA_FIELDS[$model::class])) {
            $field = self::MEDIA_FIELDS[$model::class];
            $this->usages->sync($model, $field, array_filter([(int) $model->getAttribute($field)]));
        }

        $this->changed($model);
    }

    public function deleted(Model $model): void
    {
        $this->changed($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->usages->forget($model);
    }

    public function restored(Model $model): void
    {
        $this->changed($model);
    }

    private function changed(Model $model): void
    {
        $paths = LivePaths::all();

        if ($model instanceof Project) {
            $paths[] = '/projects/'.$model->slug;

            if ($model->wasChanged('slug') && is_string($model->getOriginal('slug'))) {
                $paths[] = '/projects/'.$model->getOriginal('slug');
            }
        }

        PageContentChanged::dispatch(array_values(array_unique($paths)));
    }
}
