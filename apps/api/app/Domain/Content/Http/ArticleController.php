<?php

namespace App\Domain\Content\Http;

use App\Domain\Cms\PreviewToken;
use App\Domain\Content\ArticlePresenter;
use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController
{
    public const PER_PAGE = 12;

    public function __construct(private readonly ArticlePresenter $presenter) {}

    /**
     * Published articles, newest first, filtered by ?category=, ?tag= and searched with ?q=.
     */
    public function index(Request $request): JsonResponse
    {
        $category = self::slugParam($request->query('category'));
        $tag = self::slugParam($request->query('tag'));
        $search = mb_substr(trim((string) (is_string($request->query('q')) ? $request->query('q') : '')), 0, 100);
        $page = max(1, min(1000, (int) $request->query('page', 1)));

        $query = Article::query()->published()
            ->when($category !== null, fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('type', Category::TYPE_ARTICLE)->where('slug', $category)))
            ->when($tag !== null, fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('slug', $tag)))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('body', 'like', $like));
            });

        $results = $query->with(['cover.variants', 'categories'])->latest('published_at')->latest('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        return response()->json([
            'data' => array_values(array_map(fn (Article $a) => $this->presenter->card($a), $results->items())),
            'meta' => ['page' => $results->currentPage(), 'lastPage' => $results->lastPage(), 'total' => $results->total()],
            'filters' => [
                'categories' => Category::query()->where('type', Category::TYPE_ARTICLE)
                    ->whereHas('articles', fn ($q) => $q->published())
                    ->withCount(['articles' => fn ($q) => $q->published()])
                    ->orderBy('sort_order')->orderBy('name')->get()
                    ->map(fn (Category $c) => [...$c->toPublic(), 'count' => (int) $c->getAttribute('articles_count')])->values()->all(),
                'tags' => Tag::query()
                    ->whereHas('articles', fn ($q) => $q->published())
                    ->withCount(['articles' => fn ($q) => $q->published()])
                    ->orderByDesc('articles_count')->orderBy('name')->limit(30)->get()
                    ->map(fn (Tag $t) => [...$t->toPublic(), 'count' => (int) $t->getAttribute('articles_count')])->values()->all(),
                'active' => ['category' => $category, 'tag' => $tag, 'q' => $search === '' ? null : $search],
            ],
        ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    public function show(string $slug): JsonResponse
    {
        $article = Article::query()->published()->where('slug', $slug)->first();

        if ($article === null) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Article not found.']], 404);
        }

        return response()->json(['data' => $this->presenter->full($article)])
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    /** Unpublished (or scheduled) article for a signed preview link. Never cached or indexed. */
    public function preview(Request $request, PreviewToken $tokens): JsonResponse
    {
        $ulid = $tokens->verify($request->query('token'), PreviewToken::ARTICLE);
        $article = $ulid ? Article::query()->where('ulid', $ulid)->first() : null;

        if ($article === null) {
            return response()->json(['error' => ['code' => 'preview_expired', 'message' => 'This preview link is invalid or has expired.']], 404)
                ->header('Cache-Control', 'no-store')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response()->json(['data' => $this->presenter->full($article, draft: true)])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private static function slugParam(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) && strlen($value) <= 100 ? $value : null;
    }
}
