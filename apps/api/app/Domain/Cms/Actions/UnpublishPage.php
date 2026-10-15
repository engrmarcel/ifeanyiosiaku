<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\Models\Page;
use App\Domain\Media\MediaUsageRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Takes a page off the website. Its draft and revision history are kept. */
class UnpublishPage
{
    public function __construct(
        private readonly MediaUsageRecorder $usages,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Page $page, ?User $actor): Page
    {
        if ($page->is_homepage) {
            throw ValidationException::withMessages(['page' => 'The homepage cannot be unpublished. Choose another homepage first.']);
        }

        $path = $page->path();

        DB::transaction(function () use ($page, $actor) {
            $page->forceFill([
                'status' => PageStatus::Draft,
                'published_revision_id' => null,
                'unpublish_at' => null,
                'updated_by' => $actor?->getKey(),
            ])->save();

            $this->usages->sync($page, 'published', []);
            $this->audit->record('page.unpublished', $page, ['slug' => $page->slug], $actor);
        });

        PageContentChanged::dispatch([$path]);

        return $page;
    }
}
