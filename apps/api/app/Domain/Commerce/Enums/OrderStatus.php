<?php

namespace App\Domain\Commerce\Enums;

/**
 * Order lifecycle (REQ-COM-5). Allowed moves:
 *
 *   pending ──paid──▶ paid ──refund──▶ refunded | partially_refunded ──▶ refunded
 *      │
 *      ├──cancel──▶ cancelled   (by the customer or an administrator)
 *      └──expire──▶ expired     (unpaid after the payment window)
 *
 * Nothing leaves cancelled, expired or refunded. Payment is only ever recorded on a pending order,
 * so the same order can never be paid (or fulfilled) twice.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled, self::Expired],
            self::Paid => [self::PartiallyRefunded, self::Refunded],
            self::PartiallyRefunded => [self::PartiallyRefunded, self::Refunded],
            self::Cancelled, self::Expired, self::Refunded => [],
        };
    }

    public function canBecome(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::PartiallyRefunded => 'Partly refunded',
            self::Refunded => 'Refunded',
        };
    }

    /** Whether the buyer has (or had) paid. */
    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded, self::Refunded], true);
    }
}
