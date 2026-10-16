<?php

namespace App\Domain\Commerce\Orders;

use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\PrivacyHash;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Models\CouponRedemption;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Pricing\Pricer;
use App\Domain\Commerce\Pricing\QuoteLine;
use App\Domain\Commerce\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a cart into an order (REQ-COM-4). Prices, availability and the coupon are recalculated
 * here, inside one transaction with the coupon row locked, so the order reflects the server's
 * numbers only and a limited coupon cannot be over-used by simultaneous checkouts.
 */
class Checkout
{
    public function __construct(
        private readonly Pricer $pricer,
        private readonly Store $store,
        private readonly OrderNumbers $numbers,
        private readonly OrderTransitions $transitions,
        private readonly AuditLogger $audit,
    ) {}

    public function place(User $user, Cart $cart, string $billingName, Request $request): Order
    {
        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Please confirm your email address before buying. Receipts and downloads are sent to it.']);
        }

        $billingName = trim($billingName);
        if ($billingName === '' || mb_strlen($billingName) > 120 || preg_match('/[\r\n]/', $billingName)) {
            throw ValidationException::withMessages(['billing_name' => 'Enter the name to put on your receipt.']);
        }

        $order = DB::transaction(function () use ($user, $cart, $billingName, $request) {
            $cart = Cart::query()->whereKey($cart->getKey())->lockForUpdate()->firstOrFail();
            $products = $cart->items()->with('product.prices')->get()->map(fn ($i) => $i->product)->filter()->values();
            $currency = $this->store->currency($cart->currency);
            $quote = $this->pricer->quote($products, $currency, $cart->coupon_code, $user, lockCoupon: true);

            if ($quote->lines === []) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            if (! $quote->purchasable()) {
                throw ValidationException::withMessages(['cart' => 'Some items can no longer be bought. Please review your cart.']);
            }

            if ($cart->coupon_code !== null && $quote->couponProblem !== null) {
                throw ValidationException::withMessages(['coupon' => $quote->couponProblem]);
            }

            $order = Order::query()->create([
                'number' => $this->numbers->next(),
                'user_id' => $user->getKey(),
                'status' => OrderStatus::Pending,
                'currency' => $currency,
                'subtotal_amount' => $quote->subtotal(),
                'discount_amount' => $quote->discount(),
                'total_amount' => $quote->total(),
                'coupon_id' => $quote->coupon?->getKey(),
                'coupon_code' => $quote->coupon?->code,
                'billing_name' => $billingName,
                'billing_email' => $user->email,
                'ip_hash' => PrivacyHash::of($request->ip(), 'order-ip'),
                'expires_at' => now()->addMinutes(max(5, (int) config('platform.defaults')['commerce.order_expiry_minutes'])),
            ]);

            foreach ($quote->lines as $line) {
                /** @var QuoteLine $line */
                $order->items()->create([
                    'product_id' => $line->product->getKey(),
                    'product_snapshot' => [
                        'title' => $line->product->title,
                        'type' => $line->product->type->value,
                        'slug' => $line->product->slug,
                        'version' => $line->product->versions()->value('version_label'),
                    ],
                    'unit_amount' => (int) $line->unitAmount,
                    'quantity' => 1,
                    'discount_amount' => $line->discountAmount,
                    'total_amount' => $line->totalAmount(),
                ]);
            }

            if ($quote->coupon !== null && $quote->discount() > 0) {
                CouponRedemption::query()->create([
                    'coupon_id' => $quote->coupon->getKey(),
                    'order_id' => $order->getKey(),
                    'user_id' => $user->getKey(),
                    'discount_amount' => $quote->discount(),
                ]);
            }

            $cart->items()->delete();
            $cart->forceFill(['coupon_code' => null])->save();

            return $order;
        });

        $this->audit->record('order.created', $order, [
            'number' => $order->number, 'total' => $order->total_amount, 'currency' => $order->currency, 'coupon' => $order->coupon_code,
        ], $user);

        // Nothing to pay (free products or a 100% coupon): complete straight away.
        if ($order->isFree()) {
            $this->transitions->markPaid($order, 'free', $user);
        }

        return $order->refresh();
    }
}
