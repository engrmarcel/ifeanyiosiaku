<?php

namespace App\Domain\Commerce\Pricing;

use App\Domain\Commerce\Models\Coupon;
use App\Domain\Commerce\Models\CouponRedemption;
use App\Domain\Commerce\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Calculates what a set of products costs right now (REQ-COM-4): current prices, availability and
 * coupon rules, in exact minor units. Discounts are rounded down to the minor unit and never make a
 * line negative.
 */
class Pricer
{
    /**
     * @param  Collection<int, Product>  $products
     * @param  bool  $lockCoupon  lock the coupon row while counting its uses (inside a checkout transaction)
     */
    public function quote(Collection $products, string $currency, ?string $couponCode = null, ?User $user = null, bool $lockCoupon = false): Quote
    {
        $lines = [];

        foreach ($products as $product) {
            $product->loadMissing('prices');
            $lines[] = match (true) {
                ! $product->isLive() => new QuoteLine($product, null, problem: 'This product is no longer available.'),
                $product->is_free => new QuoteLine($product, 0),
                default => ($price = $product->priceIn($currency)) !== null
                    ? new QuoteLine($product, $price->amount)
                    : new QuoteLine($product, null, problem: "Not sold in {$currency}."),
            };
        }

        if ($couponCode === null || trim($couponCode) === '') {
            return new Quote($currency, $lines);
        }

        $query = Coupon::query()->where('code', Coupon::normalise($couponCode));
        $coupon = $lockCoupon ? $query->lockForUpdate()->first() : $query->first();

        if ($coupon === null) {
            return new Quote($currency, $lines, null, 'This code is not valid.');
        }

        $problem = $this->couponProblem($coupon, $currency, $lines, $user);

        if ($problem === null) {
            $this->applyDiscount($coupon, $lines);
        }

        return new Quote($currency, $lines, $coupon, $problem);
    }

    /**
     * Why the coupon cannot be used, or null.
     *
     * @param  list<QuoteLine>  $lines
     */
    private function couponProblem(Coupon $coupon, string $currency, array $lines, ?User $user): ?string
    {
        if (! $coupon->is_active || ($coupon->starts_at !== null && $coupon->starts_at->isFuture())) {
            return 'This code is not valid.';
        }

        if ($coupon->ends_at !== null && $coupon->ends_at->isPast()) {
            return 'This code has expired.';
        }

        if ($coupon->type === Coupon::FIXED && $coupon->currency !== $currency) {
            return "This code only works for orders in {$coupon->currency}.";
        }

        $eligible = array_filter($lines, fn (QuoteLine $l) => $this->eligible($coupon, $l));
        $eligibleSubtotal = array_sum(array_map(fn (QuoteLine $l) => (int) $l->unitAmount, $eligible));

        if ($eligibleSubtotal === 0) {
            return 'This code does not apply to the items in your cart.';
        }

        if ($coupon->min_subtotal !== null && $eligibleSubtotal < $coupon->min_subtotal) {
            return 'Your order does not reach the minimum amount for this code.';
        }

        $uses = CouponRedemption::query()->where('coupon_id', $coupon->getKey())->whereNull('released_at');

        if ($coupon->max_uses !== null && (clone $uses)->count() >= $coupon->max_uses) {
            return 'This code has been fully used.';
        }

        if ($coupon->max_uses_per_user !== null && $user !== null
            && (clone $uses)->where('user_id', $user->getKey())->count() >= $coupon->max_uses_per_user) {
            return 'You have already used this code.';
        }

        return null;
    }

    private function eligible(Coupon $coupon, QuoteLine $line): bool
    {
        if (! $line->available() || (int) $line->unitAmount === 0) {
            return false;
        }

        $only = $coupon->applies_to;

        return $only === null || $only === [] || in_array($line->product->getKey(), array_map('intval', $only), true);
    }

    /**
     * @param  list<QuoteLine>  $lines
     */
    private function applyDiscount(Coupon $coupon, array $lines): void
    {
        $eligible = array_values(array_filter($lines, fn (QuoteLine $l) => $this->eligible($coupon, $l)));

        if ($coupon->type === Coupon::PERCENT) {
            $percent = max(0, min(100, $coupon->value));
            foreach ($eligible as $line) {
                $line->discountAmount = intdiv((int) $line->unitAmount * $percent, 100);
            }

            return;
        }

        // Fixed amount: shared across eligible lines in proportion to their price, capped at their total.
        $subtotal = array_sum(array_map(fn (QuoteLine $l) => (int) $l->unitAmount, $eligible));
        $remaining = min($coupon->value, $subtotal);
        $count = count($eligible);

        foreach ($eligible as $i => $line) {
            $share = $i === $count - 1
                ? $remaining
                : min((int) $line->unitAmount, intdiv($coupon->value * (int) $line->unitAmount, max(1, $subtotal)));
            $share = min($share, (int) $line->unitAmount, $remaining);
            $line->discountAmount = $share;
            $remaining -= $share;
        }
    }
}
