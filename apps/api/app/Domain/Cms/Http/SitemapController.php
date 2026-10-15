<?php

namespace App\Domain\Cms\Http;

use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use App\Domain\Portfolio\Models\Project;
use App\Domain\Settings\SiteConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public addresses for the sitemap: published pages that allow indexing and point to themselves,
 * and published case studies. Empty when indexing is switched off. Cleared on every publish.
 */
class SitemapController
{
    public function __invoke(SiteConfig $site): JsonResponse
    {
        $entries = Cache::remember('cms:sitemap', now()->addDay(), function () use ($site) {
            if (! $site->indexing()) {
                return [];
            }

            $entries = [];

            $pages = Page::query()->with('publishedRevision')->where('status', PageStatus::Published)
                ->whereNotNull('published_revision_id')->orderByDesc('is_homepage')->orderBy('slug')->get();

            foreach ($pages as $page) {
                $seo = is_array($page->publishedRevision?->seo) ? $page->publishedRevision->seo : [];

                if (($seo['noindex'] ?? false) || filled($seo['canonical_url'] ?? null)) {
                    continue;
                }

                $entries[] = [
                    'path' => $page->path(),
                    'lastModified' => ($page->publishedRevision->created_at ?? $page->updated_at)?->toIso8601String(),
                ];
            }

            foreach (Project::query()->published()->orderBy('sort_order')->get(['slug', 'updated_at']) as $project) {
                $entries[] = ['path' => '/projects/'.$project->slug, 'lastModified' => $project->updated_at?->toIso8601String()];
            }

            return $entries;
        });

        return response()->json(['data' => ['entries' => $entries]]);
    }
}
