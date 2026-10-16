<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Models\Redirect;

/**
 * When published content moves to a new address, the old address permanently redirects to the
 * new one, so links and search results keep working (REQ-CMS-10).
 */
class AutomaticRedirects
{
    public static function moved(string $from, string $to, ?int $actorId = null): void
    {
        if ($from === $to) {
            return;
        }

        // The new address is live content now, so it must not redirect anywhere (avoids loops).
        Redirect::query()->where('from_path', $to)->delete();

        Redirect::query()->updateOrCreate(['from_path' => $from], [
            'to_path' => $to, 'status_code' => 301, 'is_automatic' => true, 'created_by' => $actorId,
        ]);

        // Earlier redirects pointing at the old address now jump straight to the new one.
        Redirect::query()->where('to_path', $from)->update(['to_path' => $to]);
    }
}
