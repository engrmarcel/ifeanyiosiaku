<?php

namespace App\Domain\Commerce;

use App\Domain\Settings\SettingsRepository;

/** Store-wide settings: which currencies are on sale and the default download limit. */
class Store
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** @return list<string> */
    public function currencies(): array
    {
        $configured = $this->settings->get('commerce.currencies');
        $list = array_values(array_filter(
            is_array($configured) ? $configured : ['NGN'],
            fn ($c) => is_string($c) && array_key_exists($c, Money::SUPPORTED),
        ));

        return $list === [] ? ['NGN'] : $list;
    }

    public function defaultCurrency(): string
    {
        return $this->currencies()[0];
    }

    /** The requested currency if it is on sale, otherwise the default. */
    public function currency(?string $requested): string
    {
        $requested = strtoupper((string) $requested);

        return in_array($requested, $this->currencies(), true) ? $requested : $this->defaultCurrency();
    }

    public function downloadLimit(): int
    {
        return max(1, (int) $this->settings->get('commerce.download_limit', 5));
    }
}
