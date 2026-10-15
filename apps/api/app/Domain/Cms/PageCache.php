<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Events\PageContentChanged;
use Illuminate\Support\Facades\Cache;

/** Cache for published page payloads. Cleared whenever visible content changes. */
class PageCache
{
    public static function key(string $path): string
    {
        return 'cms:page:'.sha1($path);
    }

    public function forget(PageContentChanged $event): void
    {
        foreach ($event->paths as $path) {
            Cache::forget(self::key($path));
        }

        Cache::forget('cms:sitemap');
    }
}
