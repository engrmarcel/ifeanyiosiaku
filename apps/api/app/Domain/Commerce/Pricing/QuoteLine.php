<?php

namespace App\Domain\Commerce\Pricing;

use App\Domain\Commerce\Models\Product;

/** One product in a quote. Amounts are minor units; unavailable lines are never charged. */
final class QuoteLine
{
    public function __construct(
        public readonly Product $product,
        public readonly ?int $unitAmount,
        public int $discountAmount = 0,
        public readonly ?string $problem = null,
    ) {}

    public function available(): bool
    {
        return $this->problem === null && $this->unitAmount !== null;
    }

    public function totalAmount(): int
    {
        return $this->available() ? max(0, (int) $this->unitAmount - $this->discountAmount) : 0;
    }
}
