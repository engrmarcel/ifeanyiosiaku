<?php

namespace App\Domain\Cms\Http;

use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\Redirect;
use App\Domain\Cms\PageCache;
use App\Domain\Cms\PagePresenter;
use App\Domain\Cms\PreviewToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PageController
{
    public function __construct(private readonly PagePresenter $presenter) {}

    /** The published homepage. */
    public function home(): JsonResponse
    {
        $payload = Cache::remember(PageCache::key('/'), now()->addDay(), function () {
            $page = Page::query()->with('publishedRevision')->where('is_homepage', true)
                ->where('status', PageStatus::Published)->whereNotNull('published_revision_id')->first();

            return $page ? $this->presenter->present($page) : null;
        });

        return $payload === null ? $this->notFound(null) : $this->ok($payload);
    }

    /** A published page by its address, e.g. "about" or "services/training". */
    public function show(string $slug): JsonResponse
    {
        $slug = strtolower(trim($slug, '/'));
        $path = '/'.$slug;

        $payload = Cache::remember(PageCache::key($path), now()->addDay(), function () use ($slug) {
            $page = Page::query()->with('publishedRevision')->where('status', PageStatus::Published)
                ->whereNotNull('published_revision_id')
                ->whereHas('publishedRevision', fn ($q) => $q->where('slug', $slug))
                ->first();

            // The homepage is served at "/", so its own slug redirects there.
            return $page && ! $page->is_homepage ? $this->presenter->present($page) : null;
        });

        return $payload === null ? $this->notFound($path) : $this->ok($payload);
    }

    /** Unpublished draft for a signed preview link. Never cached or indexed. */
    public function preview(Request $request, PreviewToken $tokens): JsonResponse
    {
        $ulid = $tokens->verify($request->query('token'));
        $page = $ulid ? Page::query()->where('ulid', $ulid)->first() : null;

        if ($page === null) {
            return response()->json(['error' => ['code' => 'preview_expired', 'message' => 'This preview link is invalid or has expired.']], 404)
                ->header('Cache-Control', 'no-store')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response()->json(['data' => $this->presenter->present($page, draft: true)])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** @param array<string, mixed> $payload */
    private function ok(array $payload): JsonResponse
    {
        return response()->json(['data' => $payload])
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    private function notFound(?string $path): JsonResponse
    {
        $error = ['code' => 'not_found', 'message' => 'Page not found.'];

        if ($path !== null) {
            $homepage = Page::query()->where('is_homepage', true)->where('status', PageStatus::Published)->value('slug');
            $redirect = $homepage !== null && '/'.$homepage === $path
                ? ['to' => '/', 'status' => 301]
                : Redirect::query()->where('from_path', $path)->first(['to_path', 'status_code', 'id']);

            if ($redirect instanceof Redirect) {
                Redirect::query()->whereKey($redirect->getKey())->increment('hits');
                $redirect = ['to' => $redirect->to_path, 'status' => $redirect->status_code];
            }

            if ($redirect !== null) {
                $error['redirect'] = $redirect;
            }
        }

        return response()->json(['error' => $error], 404);
    }
}
