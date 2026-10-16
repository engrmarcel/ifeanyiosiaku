<?php

namespace App\Domain\Cms\Http;

use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use App\Domain\Commerce\Models\Product;
use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\Publication;
use App\Domain\Portfolio\Models\Project;
use App\Domain\Settings\Addons;
use App\Domain\Settings\Enums\Addon;
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

            $articles = Article::query()->published()->latest('published_at')->get(['slug', 'seo', 'updated_at']);
            if ($articles->isNotEmpty()) {
                $entries[] = ['path' => '/articles', 'lastModified' => $articles->max('updated_at')?->toIso8601String()];
            }
            foreach ($articles as $article) {
                if (! ($article->seo['noindex'] ?? false)) {
                    $entries[] = ['path' => $article->path(), 'lastModified' => $article->updated_at?->toIso8601String()];
                }
            }

            $publications = Publication::query()->published()->orderBy('sort_order')->get(['slug', 'seo', 'updated_at']);
            if ($publications->isNotEmpty()) {
                $entries[] = ['path' => '/publications', 'lastModified' => $publications->max('updated_at')?->toIso8601String()];
            }
            foreach ($publications as $publication) {
                if (! ($publication->seo['noindex'] ?? false)) {
                    $entries[] = ['path' => $publication->path(), 'lastModified' => $publication->updated_at?->toIso8601String()];
                }
            }

            // Store products, only while the Commerce add-on is on (the cache is cleared when it changes).
            if (app(Addons::class)->enabled(Addon::Commerce)) {
                $products = Product::query()->published()->orderByDesc('is_featured')->orderBy('sort_order')->get(['slug', 'seo', 'updated_at']);
                if ($products->isNotEmpty()) {
                    $entries[] = ['path' => '/store', 'lastModified' => $products->max('updated_at')?->toIso8601String()];
                }
                foreach ($products as $product) {
                    if (! ($product->seo['noindex'] ?? false)) {
                        $entries[] = ['path' => $product->path(), 'lastModified' => $product->updated_at?->toIso8601String()];
                    }
                }
            }

            return $entries;
        });

        return response()->json(['data' => ['entries' => $entries]]);
    }
}
