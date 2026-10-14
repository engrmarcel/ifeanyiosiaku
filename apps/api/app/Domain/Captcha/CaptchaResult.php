<?php

namespace App\Domain\Captcha;

final readonly class CaptchaResult
{
    // Failure reasons. "unavailable" means the provider could not be reached or is misconfigured;
    // forms fail closed in that case.
    public const MISSING = 'missing';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const REPLAYED = 'replayed';

    public const MISMATCH = 'mismatch';

    public const TOO_FAST = 'too_fast';

    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public bool $passed,
        public ?string $reason = null,
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    public static function fail(string $reason): self
    {
        return new self(false, $reason);
    }
}
