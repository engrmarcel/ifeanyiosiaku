<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageRevision;
use App\Models\User;

/** Snapshots the page's current draft. Old draft snapshots are pruned; published ones are kept. */
class RecordPageRevision
{
    public function handle(Page $page, string $kind, ?User $actor, ?string $note = null): PageRevision
    {
        $number = (int) PageRevision::query()->where('page_id', $page->getKey())->lockForUpdate()->max('number') + 1;

        $revision = PageRevision::query()->create([
            'page_id' => $page->getKey(),
            'number' => $number,
            'kind' => $kind,
            'title' => $page->title,
            'slug' => $page->slug,
            'blocks' => $page->blocks ?? [],
            'seo' => $page->seo ?? [],
            'schema_version' => Block::VERSION,
            'note' => $note,
            'created_by' => $actor?->getKey(),
        ]);

        if ($kind === 'draft') {
            $keep = (int) config('cms.max_draft_revisions', 50);
            $stale = PageRevision::query()
                ->where('page_id', $page->getKey())
                ->where('kind', 'draft')
                ->orderByDesc('number')
                ->skip($keep)
                ->take(PHP_INT_MAX)
                ->pluck('id');

            PageRevision::query()->whereIn('id', $stale)->delete();
        }

        return $revision;
    }
}
