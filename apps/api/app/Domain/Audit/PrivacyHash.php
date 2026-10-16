<?php

namespace App\Domain\Audit;

/**
 * Keyed one-way hash for values we need to compare (e.g. "same IP address", "same browser") but
 * must not store in readable form. Uses the application key, so hashes cannot be reversed by
 * guessing common values without it.
 */
class PrivacyHash
{
    public static function of(?string $value, string $purpose): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash_hmac('sha256', $purpose.'|'.$value, (string) config('app.key'));
    }
}
