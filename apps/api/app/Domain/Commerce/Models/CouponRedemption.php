<?php

namespace App\Domain\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A coupon used by an order. Released (no longer counted) when the order is cancelled or expires.
 *
 * @property int $coupon_id
 * @property int $order_id
 * @property int $user_id
 * @property int $discount_amount
 * @property Carbon|null $released_at
 */
class CouponRedemption extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
