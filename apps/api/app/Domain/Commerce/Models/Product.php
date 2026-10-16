<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Commerce\CatalogueObserver;
use App\Domain\Commerce\Enums\ProductType;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Tag;
use App\Domain\Media\Models\MediaAsset;
use App\Policies\ProductPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Something the store sells (or gives away). Visible once published and its publish time has passed.
 *
 * @property string $ulid
 * @property ProductType $type
 * @property string $slug
 * @property string $title
 * @property string|null $summary
 * @property string|null $description
 * @property int|null $cover_media_id
 * @property int|null $preview_media_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property bool $is_free
 * @property bool $is_featured
 * @property string|null $licence_terms
 * @property array<string, mixed>|null $seo
 * @property int $sort_order
 * @property Carbon|null $updated_at
 */
#[ObservedBy(CatalogueObserver::class)]
#[UsePolicy(ProductPolicy::class)]
class Product extends Model
{
    use HasUlids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $guarded = ['id', 'ulid'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => self::STATUS_DRAFT, 'type' => 'pdf', 'is_free' => false, 'is_featured' => false, 'sort_order' => 0];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'published_at' => 'datetime',
            'is_free' => 'boolean',
            'is_featured' => 'boolean',
            'coin_redeemable' => 'boolean',
            'seo' => 'array',
        ];
    }

    public function path(): string
    {
        return '/store/'.$this->slug;
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_at !== null && $this->published_at->lte(now());
    }

    /** Price in a currency, or null when the product is not sold in it. Free products have none. */
    public function priceIn(string $currency): ?ProductPrice
    {
        if ($this->is_free) {
            return null;
        }

        return $this->prices->firstWhere('currency', $currency);
    }

    /** @return HasMany<ProductPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /** @return HasMany<ProductVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class)->orderByDesc('released_at')->orderByDesc('id');
    }

    /** @return HasMany<ProductFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Products included in a bundle.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function bundleItems(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'bundle_items', 'bundle_product_id', 'product_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    /**
     * A free sample (image or PDF from the public media library).
     *
     * @return BelongsTo<MediaAsset, $this>
     */
    public function preview(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'preview_media_id');
    }

    /** @return MorphToMany<Category, $this> */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')->orderBy('sort_order')->orderBy('name');
    }

    /** @return MorphToMany<Tag, $this> */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->orderBy('name');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
