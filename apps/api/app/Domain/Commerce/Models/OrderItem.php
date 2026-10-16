<?php

namespace App\Domain\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product in an order, with the price and details as they were at purchase time.
 *
 * @property int $order_id
 * @property int $product_id
 * @property array<string, mixed> $product_snapshot
 * @property int $unit_amount
 * @property int $quantity
 * @property int $discount_amount
 * @property int $total_amount
 * @property-read Order $order always present: order_id is a required foreign key
 */
class OrderItem extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['product_snapshot' => 'array'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
