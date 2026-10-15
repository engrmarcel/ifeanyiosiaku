<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Makes a published page the homepage (exactly one page is the homepage). */
class SetHomepage
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Page $page, ?User $actor): void
    {
        if (! $page->isLive()) {
            throw ValidationException::withMessages(['page' => 'Publish the page before making it the homepage.']);
        }

        $paths = DB::transaction(function () use ($page, $actor) {
            $previous = Page::query()->where('is_homepage', true)->whereKeyNot($page->getKey())->lockForUpdate()->get();
            $paths = ['/', '/'.$page->slug];

            foreach ($previous as $old) {
                $old->forceFill(['is_homepage' => false])->save();
                $paths[] = '/'.$old->slug;
            }

            $page->forceFill(['is_homepage' => true])->save();
            $this->audit->record('page.set_homepage', $page, ['slug' => $page->slug], $actor);

            return $paths;
        });

        PageContentChanged::dispatch(array_values(array_unique($paths)));
    }
}
