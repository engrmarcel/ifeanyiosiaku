<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Commerce\CatalogueObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A released version of a product. Buyers keep access to the files of their version and newer ones
 * (licence terms permitting).
 *
 * @property int $product_id
 * @property string $version_label
 * @property string|null $changelog
 * @property Carbon $released_at
 */
#[ObservedBy(CatalogueObserver::class)]
class ProductVersion extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['released_at' => 'date'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }
}
