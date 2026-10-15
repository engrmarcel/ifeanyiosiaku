<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cms\Blocks\BlockRegistry;
use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\Redirect;
use App\Domain\Media\MediaUsageRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Makes the current draft live: snapshots it as an immutable "publish" revision and points the
 * page at it. If a published page's address changed, the old address redirects to the new one.
 */
class PublishPage
{
    public function __construct(
        private readonly RecordPageRevision $revisions,
        private readonly BlockRegistry $blocks,
        private readonly MediaUsageRecorder $usages,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Page $page, ?User $actor): Page
    {
        // Re-validate in case block rules changed since the draft was saved.
        $this->blocks->prepare($page->blocks ?? []);

        $paths = DB::transaction(function () use ($page, $actor) {
            $page = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            $previous = $page->publishedRevision;
            $previousPath = $previous ? ($page->is_homepage ? '/' : '/'.$previous->slug) : null;

            $revision = $this->revisions->handle($page, 'publish', $actor);

            $page->forceFill([
                'published_revision_id' => $revision->getKey(),
                'status' => PageStatus::Published,
                'published_at' => now(),
                'publish_at' => null,
                'updated_by' => $actor?->getKey(),
            ])->save();

            $paths = [$page->path()];

            if ($previous && $previous->slug !== $page->slug && ! $page->is_homepage) {
                $from = '/'.$previous->slug;
                $to = '/'.$page->slug;
                Redirect::query()->where('from_path', $to)->delete(); // avoid loops
                Redirect::query()->updateOrCreate(['from_path' => $from], [
                    'to_path' => $to, 'status_code' => 301, 'is_automatic' => true, 'created_by' => $actor?->getKey(),
                ]);
                // Earlier automatic redirects pointing at the old address now jump straight to the new one.
                Redirect::query()->where('to_path', $from)->update(['to_path' => $to]);
                $paths[] = $from;
            } elseif ($previousPath !== null) {
                $paths[] = $previousPath;
            }

            $this->usages->sync($page, 'published', [
                ...$this->blocks->mediaIds($revision->blocks ?? []),
                ...array_filter([$revision->seo['og_media'] ?? null]),
            ]);
            $this->audit->record('page.published', $page, ['slug' => $page->slug, 'revision' => $revision->number], $actor);

            return array_values(array_unique($paths));
        });

        PageContentChanged::dispatch($paths);

        return $page->refresh();
    }
}
