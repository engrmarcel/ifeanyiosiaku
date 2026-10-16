<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\Models\Page;
use App\Domain\Settings\Events\SettingChanged;

/** Public paths of every published page, for refreshing content shown across the site. */
class LivePaths
{
    /** Settings that are displayed inside page content. */
    private const CONTENT_SETTING_PREFIXES = ['profile.', 'site.', 'addons.', 'newsletter.'];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(Page::query()
            ->where('status', PageStatus::Published)
            ->get(['slug', 'is_homepage'])
            ->map(fn (Page $page) => $page->path())
            ->all());
    }

    public function settingChanged(SettingChanged $event): void
    {
        foreach (self::CONTENT_SETTING_PREFIXES as $prefix) {
            if (str_starts_with($event->key, $prefix)) {
                PageContentChanged::dispatch(self::all());

                return;
            }
        }
    }
}
