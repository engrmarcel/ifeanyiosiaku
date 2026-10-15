<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Copies an earlier revision back into the working draft. The live page is unchanged until the
 * restored draft is published.
 */
class RestorePageRevision
{
    public function __construct(
        private readonly RecordPageRevision $revisions,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Page $page, PageRevision $revision, ?User $actor): Page
    {
        if ($revision->page_id !== $page->getKey()) {
            throw new InvalidArgumentException('Revision belongs to another page.');
        }

        return DB::transaction(function () use ($page, $revision, $actor) {
            $page->forceFill([
                'title' => $revision->title,
                'slug' => $revision->slug,
                'blocks' => $revision->blocks,
                'seo' => $revision->seo,
                'updated_by' => $actor?->getKey(),
            ])->save();

            $this->revisions->handle($page, 'restore', $actor, "Restored from revision {$revision->number}");
            $this->audit->record('page.revision_restored', $page, ['revision' => $revision->number], $actor);

            return $page;
        });
    }
}
