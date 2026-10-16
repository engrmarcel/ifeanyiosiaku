<?php

namespace App\Domain\Commerce\Models;

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Money;
use App\Models\User;
use App\Policies\OrderPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer's order. Financial record: never deleted. Its status only changes through
 * OrderTransitions, which enforces the state machine in OrderStatus.
 *
 * @property string $ulid
 * @property string $number
 * @property int $user_id
 * @property OrderStatus $status
 * @property string $currency
 * @property int $subtotal_amount
 * @property int $discount_amount
 * @property int $total_amount
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property string $billing_name
 * @property string $billing_email
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 */
#[UsePolicy(OrderPolicy::class)]
class Order extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'ulid'];

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
            'status' => OrderStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        // chaperone(): each loaded item knows its order without another query (it needs the currency).
        return $this->hasMany(OrderItem::class)->orderBy('id')->chaperone();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isFree(): bool
    {
        return $this->total_amount === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublic(): array
    {
        $this->loadMissing('items');

        return [
            'id' => $this->ulid,
            'number' => $this->number,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'currency' => $this->currency,
            'subtotal' => Money::toArray($this->subtotal_amount, $this->currency),
            'discount' => Money::toArray($this->discount_amount, $this->currency),
            'total' => Money::toArray($this->total_amount, $this->currency),
            'couponCode' => $this->coupon_code,
            'expiresAt' => $this->status === OrderStatus::Pending ? $this->expires_at?->toIso8601String() : null,
            'paidAt' => $this->paid_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'items' => $this->items->map(fn (OrderItem $item) => [
                'title' => (string) ($item->product_snapshot['title'] ?? 'Product'),
                'type' => (string) ($item->product_snapshot['type'] ?? ''),
                'url' => isset($item->product_snapshot['slug']) ? '/store/'.$item->product_snapshot['slug'] : null,
                'unit' => Money::toArray($item->unit_amount, $this->currency),
                'discount' => Money::toArray($item->discount_amount, $this->currency),
                'total' => Money::toArray($item->total_amount, $this->currency),
            ])->values()->all(),
        ];
    }
}
