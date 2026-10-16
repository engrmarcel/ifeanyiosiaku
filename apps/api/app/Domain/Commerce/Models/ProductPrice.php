<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Commerce\CatalogueObserver;
use App\Domain\Commerce\Money;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Price of a product in one currency, in minor units. Checkout always recalculates from these
 * rows; prices held in a cart are never trusted.
 *
 * @property int $product_id
 * @property string $currency
 * @property int $amount
 * @property int|null $compare_at_amount
 */
#[ObservedBy(CatalogueObserver::class)]
class ProductPrice extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return array{amount: int, currency: string, formatted: string, compareAt: array{amount: int, currency: string, formatted: string}|null}
     */
    public function toPublic(): array
    {
        return [
            ...Money::toArray($this->amount, $this->currency),
            'compareAt' => $this->compare_at_amount !== null && $this->compare_at_amount > $this->amount
                ? Money::toArray($this->compare_at_amount, $this->currency)
                : null,
        ];
    }
}
