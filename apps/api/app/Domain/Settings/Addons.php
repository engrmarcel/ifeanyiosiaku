<?php

namespace App\Domain\Settings;

use App\Domain\Settings\Enums\Addon;

/** Reads whether optional add-on modules are switched on. */
class Addons
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function enabled(Addon $addon): bool
    {
        return $this->settings->bool($addon->settingKey());
    }

    /**
     * @return array<string, bool> add-on value => enabled
     */
    public function all(): array
    {
        $result = [];

        foreach (Addon::cases() as $addon) {
            $result[$addon->value] = $this->enabled($addon);
        }

        return $result;
    }
}
