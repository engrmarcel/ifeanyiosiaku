<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\Jobs\RefreshWebsitePaths;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Cache for published page payloads. Cleared whenever visible content changes. */
class PageCache
{
    public static function key(string $path): string
    {
        return 'cms:page:'.sha1($path);
    }

    public static function resolveKey(string $path): string
    {
        return 'cms:resolve:'.sha1($path);
    }

    public function forget(PageContentChanged $event): void
    {
        foreach ($event->paths as $path) {
            Cache::forget(self::key($path));
            Cache::forget(self::resolveKey($path));
        }

        Cache::forget('cms:sitemap');

        // A refresh problem must never undo or block a publish: report it and rely on the
        // website's hourly cache lifetime as the fallback.
        try {
            RefreshWebsitePaths::dispatch($event->paths);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
