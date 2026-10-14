<?php

namespace App\Domain\Captcha\Drivers;

class Turnstile extends RemoteSiteVerifyCaptcha
{
    protected function configKey(): string
    {
        return 'turnstile';
    }

    protected function accepts(array $response, string $purpose): bool
    {
        if (! $this->hostnameMatches($response)) {
            return false;
        }

        return ! isset($response['action']) || $response['action'] === '' || $response['action'] === $purpose;
    }
}
