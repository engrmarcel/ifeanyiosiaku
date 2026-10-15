<?php

namespace App\Domain\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something visitors can see changed (publish, unpublish, homepage switch). Listeners clear caches
 * and ask the website to refresh the affected paths.
 */
class PageContentChanged
{
    use Dispatchable;

    /**
     * @param  list<string>  $paths  public paths that must be refreshed, e.g. ["/", "/about"]
     */
    public function __construct(public readonly array $paths) {}
}
