<?php

namespace App\Domain\Settings;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime-editable, non-secret settings. Values fall back to config('platform.defaults').
 */
class SettingsRepository
{
    private const CACHE_KEY = 'platform:settings';

    public function __construct(private readonly AuditLogger $audit) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->all();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        // Keys contain dots, so read the defaults array directly rather than via dot notation.
        return config('platform.defaults', [])[$key] ?? $default;
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function set(string $key, mixed $value, ?User $actor = null): void
    {
        $previous = $this->get($key);

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'is_public' => in_array($key, config('platform.public_settings', []), true),
                'updated_by' => $actor?->id,
            ],
        );

        Cache::forget(self::CACHE_KEY);

        $this->audit->record('settings.updated', context: [
            'key' => $key,
            'from' => $previous,
            'to' => $value,
        ], actor: $actor);
    }

    /**
     * Settings safe to expose to anonymous visitors.
     *
     * @return array<string, mixed>
     */
    public function public(): array
    {
        $result = [];

        foreach (config('platform.public_settings', []) as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->pluck('value', 'key')
            ->all());
    }
}
