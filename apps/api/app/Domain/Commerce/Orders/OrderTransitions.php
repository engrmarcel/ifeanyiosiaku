<?php

namespace App\Domain\Commerce\Orders;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Events\OrderPaid;
use App\Domain\Commerce\Models\CouponRedemption;
use App\Domain\Commerce\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only way an order's status changes (REQ-COM-5). Each move locks the order row, checks the
 * state machine in OrderStatus, and is audit-logged, so concurrent attempts (e.g. two payment
 * confirmations) cannot both succeed.
 */
class OrderTransitions
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Pending → paid. Returns false (and changes nothing) if the order was already paid or closed. */
    public function markPaid(Order $order, string $via, ?User $actor = null): bool
    {
        $changed = $this->move($order, OrderStatus::Paid, ['paid_at' => now()], ['via' => $via], $actor);

        if ($changed) {
            OrderPaid::dispatch($order->refresh());
        }

        return $changed;
    }

    /** Pending → cancelled (by the customer or an administrator). */
    public function cancel(Order $order, string $reason, ?User $actor): void
    {
        if (! $this->move($order, OrderStatus::Cancelled, ['cancelled_at' => now(), 'cancel_reason' => $reason], ['reason' => $reason], $actor)) {
            throw ValidationException::withMessages(['order' => 'Only orders awaiting payment can be cancelled.']);
        }
    }

    /** Pending → expired (unpaid after the payment window). */
    public function expire(Order $order): bool
    {
        return $this->move($order, OrderStatus::Expired, ['cancelled_at' => now(), 'cancel_reason' => 'expired'], [], null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $context
     */
    private function move(Order $order, OrderStatus $to, array $attributes, array $context, ?User $actor): bool
    {
        $result = DB::transaction(function () use ($order, $to, $attributes) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canBecome($to)) {
                return null;
            }

            $locked->forceFill(['status' => $to, ...$attributes])->save();

            // A cancelled or expired order no longer uses up its coupon.
            if (in_array($to, [OrderStatus::Cancelled, OrderStatus::Expired], true)) {
                CouponRedemption::query()->where('order_id', $locked->getKey())->whereNull('released_at')->update(['released_at' => now()]);
            }

            return $from;
        });

        if ($result === null) {
            return false;
        }

        $order->refresh();
        $this->audit->record('order.'.$to->value, $order, ['number' => $order->number, 'from' => $result->value, ...$context], $actor);

        return true;
    }
}
