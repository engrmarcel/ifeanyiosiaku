<?php

namespace App\Domain\Captcha;

use App\Domain\Captcha\Contracts\CaptchaVerifier;
use App\Domain\Captcha\Drivers\FirstPartyCaptcha;
use App\Domain\Captcha\Drivers\ReCaptcha;
use App\Domain\Captcha\Drivers\Turnstile;
use App\Domain\Settings\SettingsRepository;
use InvalidArgumentException;

/**
 * Resolves the active CAPTCHA driver. The administrator's choice (setting "captcha.driver")
 * takes precedence over the environment default.
 */
class CaptchaManager
{
    public const DRIVERS = ['first_party', 'recaptcha', 'turnstile'];

    public function __construct(private readonly SettingsRepository $settings) {}

    public function driver(?string $name = null): CaptchaVerifier
    {
        $name ??= (string) $this->settings->get('captcha.driver', config('captcha.driver'));

        return match ($name) {
            'first_party' => app(FirstPartyCaptcha::class),
            'recaptcha' => app(ReCaptcha::class),
            'turnstile' => app(Turnstile::class),
            default => throw new InvalidArgumentException("Unsupported CAPTCHA driver [{$name}]."),
        };
    }

    public function isValidPurpose(string $purpose): bool
    {
        return in_array($purpose, config('captcha.purposes'), true);
    }
}
