<?php

namespace App\Domain\Commerce;

use App\Domain\Cms\Support\RichTextSanitizer;
use App\Domain\Commerce\Models\Product;
use App\Domain\Commerce\Models\ProductFile;
use App\Domain\Commerce\Models\ProductVersion;
use App\Domain\Content\ArticlePresenter;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Tag;

/** Public shapes of a product: a card for lists and the full product page. File paths are never exposed. */
class ProductPresenter
{
    public function __construct(
        private readonly Store $store,
        private readonly RichTextSanitizer $sanitizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function card(Product $product, string $currency): array
    {
        $product->loadMissing(['cover.variants', 'prices', 'categories']);
        $price = $product->priceIn($currency);

        return [
            'id' => $product->ulid,
            'slug' => $product->slug,
            'url' => $product->path(),
            'title' => $product->title,
            'summary' => $product->summary,
            'type' => $product->type->value,
            'typeLabel' => $product->type->label(),
            'cover' => ArticlePresenter::image($product->cover),
            'isFree' => $product->is_free,
            'price' => $price?->toPublic(),
            // Not for sale in this currency (and not free): shown but cannot be bought in it.
            'available' => $product->is_free || $price !== null,
            'isFeatured' => $product->is_featured,
            'categories' => $product->categories->map(fn (Category $c) => $c->toPublic())->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function full(Product $product, string $currency): array
    {
        $product->loadMissing(['versions', 'files', 'tags', 'preview.variants', 'bundleItems.prices', 'bundleItems.cover.variants', 'bundleItems.categories']);
        $seo = is_array($product->seo) ? $product->seo : [];
        $preview = $product->preview;

        return [
            ...$this->card($product, $currency),
            'descriptionHtml' => $this->sanitizer->clean($product->description),
            'tags' => $product->tags->map(fn (Tag $t) => $t->toPublic())->values()->all(),
            'licenceTerms' => $product->licence_terms,
            'versions' => $product->versions->map(fn (ProductVersion $v) => [
                'label' => $v->version_label,
                'changelog' => $v->changelog,
                'releasedAt' => $v->released_at->toDateString(),
            ])->values()->all(),
            // What buyers get: names and sizes only. The files themselves stay private.
            'includes' => $product->files->map(fn (ProductFile $f) => [
                'label' => $f->label,
                'size' => $f->size,
                'extension' => strtolower(pathinfo($f->original_name, PATHINFO_EXTENSION)),
            ])->values()->all(),
            'bundle' => $product->bundleItems->map(fn (Product $p) => $this->card($p, $currency))->values()->all(),
            'preview' => $preview === null ? null : [
                'url' => $preview->url(),
                'kind' => $preview->isImage() ? 'image' : 'pdf',
                'name' => $preview->title ?: $preview->filename,
                'image' => ArticlePresenter::image($preview),
            ],
            'currency' => $currency,
            'currencies' => $this->store->currencies(),
            'updatedAt' => $product->updated_at?->toIso8601String(),
            'seo' => [
                'title' => $seo['title'] ?? null,
                'description' => $seo['description'] ?? $product->summary,
                'noindex' => (bool) ($seo['noindex'] ?? false),
                'image' => ArticlePresenter::image($product->cover),
            ],
            'related' => $this->related($product, $currency),
        ];
    }

    /**
     * Up to three other published products sharing a category.
     *
     * @return list<array<string, mixed>>
     */
    private function related(Product $product, string $currency): array
    {
        $categoryIds = $product->categories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return [];
        }

        return array_values(Product::query()->published()
            ->whereKeyNot($product->getKey())
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->with(['cover.variants', 'prices', 'categories'])
            ->orderByDesc('is_featured')->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Product $p) => $this->card($p, $currency))
            ->all());
    }
}
