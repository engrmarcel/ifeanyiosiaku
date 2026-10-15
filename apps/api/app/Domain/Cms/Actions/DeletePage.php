<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cms\Models\Page;
use App\Domain\Media\MediaUsageRecorder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Moves a page to the trash, taking it off the website first. Revisions are kept. */
class DeletePage
{
    public function __construct(
        private readonly UnpublishPage $unpublish,
        private readonly MediaUsageRecorder $usages,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Page $page, ?User $actor): void
    {
        if ($page->is_homepage) {
            throw ValidationException::withMessages(['page' => 'The homepage cannot be deleted. Choose another homepage first.']);
        }

        if ($page->isLive()) {
            $this->unpublish->handle($page, $actor);
        }

        $this->usages->forget($page);
        $page->delete();
        $this->audit->record('page.deleted', $page, ['slug' => $page->slug], $actor);
    }
}
