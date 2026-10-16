<?php

namespace App\Domain\Commerce;

use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;
use InvalidArgumentException;

/**
 * Money helpers. Amounts are stored as integer minor units (kobo, cents) with an ISO currency
 * (ADR-005); arithmetic goes through brick/money, never floats.
 */
final class Money
{
    /** Currencies the store can sell in. NGN is primary (Q4); USD can be switched on later. */
    public const SUPPORTED = ['NGN' => 'Nigerian naira (₦)', 'USD' => 'US dollar ($)'];

    /** "12500" or "12,500.50" typed by an editor → minor units, exactly. */
    public static function toMinor(string|int|float $major, string $currency): int
    {
        $clean = str_replace([',', ' '], '', (string) $major);

        if (! preg_match('/^\d+(\.\d+)?$/', $clean)) {
            throw new InvalidArgumentException('Enter an amount such as 12500 or 12500.50.');
        }

        try {
            // More decimals than the currency has (e.g. 12.505 naira) is refused, never rounded.
            return BrickMoney::of($clean, $currency, roundingMode: RoundingMode::Unnecessary)->getMinorAmount()->toInt();
        } catch (RoundingNecessaryException) {
            throw new InvalidArgumentException('Use at most two decimal places, e.g. 12500.50.');
        }
    }

    /** Minor units → "12500.00" for an editing form. */
    public static function toMajor(int $minor, string $currency): string
    {
        return (string) BrickMoney::ofMinor($minor, $currency)->getAmount();
    }

    /** Minor units → "₦12,500.00" / "$45.00" for display. */
    public static function format(int $minor, string $currency): string
    {
        $locale = $currency === 'NGN' ? 'en_NG' : 'en_US';

        return BrickMoney::ofMinor($minor, $currency)->formatToLocale($locale);
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public static function toArray(int $minor, string $currency): array
    {
        return ['amount' => $minor, 'currency' => $currency, 'formatted' => self::format($minor, $currency)];
    }
}
