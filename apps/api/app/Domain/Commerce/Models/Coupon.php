<?php

namespace App\Domain\Commerce\Models;

use App\Policies\ProductPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Discount code. Percent coupons take value 1–100; fixed coupons take an amount in minor units of
 * their currency and only apply to orders in that currency.
 *
 * @property string $code
 * @property string|null $description
 * @property string $type
 * @property int $value
 * @property string|null $currency
 * @property int|null $min_subtotal
 * @property int|null $max_uses
 * @property int|null $max_uses_per_user
 * @property list<int>|null $applies_to
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 */
#[UsePolicy(ProductPolicy::class)]
class Coupon extends Model
{
    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['type' => self::PERCENT, 'is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['applies_to' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    /** Codes are stored upper-case and matched case-insensitively. */
    public static function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    protected static function booted(): void
    {
        static::saving(fn (Coupon $coupon) => $coupon->code = self::normalise($coupon->code));
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
