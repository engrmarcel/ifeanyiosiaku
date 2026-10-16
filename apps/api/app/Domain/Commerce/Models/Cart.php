<?php

namespace App\Domain\Commerce\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shopping cart: one per signed-in user, or one per browser for visitors (identified by a
 * random cookie whose hash is stored). It holds products only: prices are always recalculated.
 *
 * @property string $ulid
 * @property int|null $user_id
 * @property string|null $guest_token_hash
 * @property string $currency
 * @property string|null $coupon_code
 */
class Cart extends Model
{
    use HasUlids, MassPrunable;

    protected $guarded = ['id', 'ulid'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Visitors' carts untouched for 30 days are removed.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->whereNull('user_id')->where('updated_at', '<', now()->subDays(30));
    }
}
