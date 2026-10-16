<?php

namespace App\Domain\Commerce\Http;

use App\Domain\Commerce\Enums\ProductType;
use App\Domain\Commerce\Models\Product;
use App\Domain\Commerce\ProductPresenter;
use App\Domain\Commerce\Store;
use App\Domain\Content\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Public store catalogue. Behind the "commerce" add-on switch (ADR-025). */
class StoreController
{
    public const PER_PAGE = 12;

    public function __construct(
        private readonly ProductPresenter $presenter,
        private readonly Store $store,
    ) {}

    /** Published products: ?category=, ?type=, ?q=, ?page=, ?currency=. Featured first, then newest. */
    public function index(Request $request): JsonResponse
    {
        $currency = $this->store->currency(is_string($request->query('currency')) ? $request->query('currency') : null);
        $category = self::slug($request->query('category'));
        $type = ProductType::tryFrom((string) (is_string($request->query('type')) ? $request->query('type') : ''));
        $search = mb_substr(trim(is_string($request->query('q')) ? $request->query('q') : ''), 0, 100);
        $page = max(1, min(1000, (int) $request->query('page', 1)));

        $results = Product::query()->published()
            ->when($category !== null, fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('type', Category::TYPE_PRODUCT)->where('slug', $category)))
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->with(['cover.variants', 'prices', 'categories'])
            ->orderByDesc('is_featured')->orderBy('sort_order')->latest('published_at')->latest('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        $types = Product::query()->published()->distinct()->pluck('type')
            ->map(fn (ProductType $t) => ['type' => $t->value, 'label' => $t->label()])->values()->all();

        return response()->json([
            'data' => array_values(array_map(fn (Product $p) => $this->presenter->card($p, $currency), $results->items())),
            'meta' => ['page' => $results->currentPage(), 'lastPage' => $results->lastPage(), 'total' => $results->total(), 'currency' => $currency, 'currencies' => $this->store->currencies()],
            'filters' => [
                'categories' => Category::query()->where('type', Category::TYPE_PRODUCT)
                    ->whereHas('products', fn ($q) => $q->published())
                    ->withCount(['products' => fn ($q) => $q->published()])
                    ->orderBy('sort_order')->orderBy('name')->get()
                    ->map(fn (Category $c) => [...$c->toPublic(), 'count' => (int) $c->getAttribute('products_count')])->values()->all(),
                'types' => $types,
                'active' => ['category' => $category, 'type' => $type?->value, 'q' => $search === '' ? null : $search],
            ],
        ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $product = Product::query()->published()->where('slug', $slug)->first();

        if ($product === null) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Product not found.']], 404);
        }

        $currency = $this->store->currency(is_string($request->query('currency')) ? $request->query('currency') : null);

        return response()->json(['data' => $this->presenter->full($product, $currency)])
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    private static function slug(mixed $value): ?string
    {
        return is_string($value) && strlen($value) <= 100 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) ? $value : null;
    }
}
