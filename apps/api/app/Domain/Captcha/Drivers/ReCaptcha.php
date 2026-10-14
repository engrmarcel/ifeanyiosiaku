<?php

namespace App\Domain\Captcha\Drivers;

class ReCaptcha extends RemoteSiteVerifyCaptcha
{
    protected function configKey(): string
    {
        return 'recaptcha';
    }

    protected function accepts(array $response, string $purpose): bool
    {
        if (! $this->hostnameMatches($response)) {
            return false;
        }

        // reCAPTCHA v3 returns an action and score; v2 returns neither.
        if (isset($response['action']) && $response['action'] !== $purpose) {
            return false;
        }

        if (isset($response['score']) && (float) $response['score'] < config('captcha.recaptcha.min_score')) {
            return false;
        }

        return true;
    }
}
