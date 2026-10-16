<?php

namespace App\Domain\Commerce\Pricing;

use App\Domain\Commerce\Models\Coupon;
use App\Domain\Commerce\Money;
use App\Domain\Commerce\ProductPresenter;

/**
 * Prices for a set of products, calculated by the server from current catalogue prices and
 * coupon rules. Never built from amounts sent by the browser.
 */
final class Quote
{
    /**
     * @param  list<QuoteLine>  $lines
     */
    public function __construct(
        public readonly string $currency,
        public readonly array $lines,
        public readonly ?Coupon $coupon = null,
        public readonly ?string $couponProblem = null,
    ) {}

    public function subtotal(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->available() ? (int) $l->unitAmount : 0, $this->lines));
    }

    public function discount(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->available() ? $l->discountAmount : 0, $this->lines));
    }

    public function total(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->totalAmount(), $this->lines));
    }

    /** Ready to order: something in it and every line can be bought. */
    public function purchasable(): bool
    {
        return $this->lines !== [] && array_filter($this->lines, fn (QuoteLine $l) => ! $l->available()) === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublic(ProductPresenter $presenter): array
    {
        return [
            'currency' => $this->currency,
            'items' => array_map(fn (QuoteLine $l) => [
                'product' => $presenter->card($l->product, $this->currency),
                'unit' => $l->unitAmount !== null ? Money::toArray($l->unitAmount, $this->currency) : null,
                'discount' => Money::toArray($l->available() ? $l->discountAmount : 0, $this->currency),
                'total' => Money::toArray($l->totalAmount(), $this->currency),
                'problem' => $l->problem,
            ], $this->lines),
            'subtotal' => Money::toArray($this->subtotal(), $this->currency),
            'discount' => Money::toArray($this->discount(), $this->currency),
            'total' => Money::toArray($this->total(), $this->currency),
            'coupon' => $this->coupon !== null && $this->couponProblem === null
                ? ['code' => $this->coupon->code, 'description' => $this->coupon->description]
                : null,
            'couponProblem' => $this->couponProblem,
            'purchasable' => $this->purchasable(),
        ];
    }
}
