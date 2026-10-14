<?php

namespace App\Domain\Captcha\Drivers;

use App\Domain\Captcha\CaptchaResult;
use App\Domain\Captcha\Contracts\CaptchaVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared logic for providers that verify tokens through a "siteverify" endpoint
 * (Google reCAPTCHA and Cloudflare Turnstile). Fails closed when the provider is unreachable.
 */
abstract class RemoteSiteVerifyCaptcha implements CaptchaVerifier
{
    abstract protected function configKey(): string;

    /**
     * Provider-specific checks on a successful response (action, score, hostname).
     *
     * @param  array<string, mixed>  $response
     */
    abstract protected function accepts(array $response, string $purpose): bool;

    public function name(): string
    {
        return $this->configKey();
    }

    public function verify(?string $token, string $purpose, Request $request): CaptchaResult
    {
        if (! is_string($token) || $token === '' || strlen($token) > 4096) {
            return CaptchaResult::fail(CaptchaResult::MISSING);
        }

        $secret = config("captcha.{$this->configKey()}.secret_key");

        if (! $secret) {
            Log::error('CAPTCHA provider selected without a secret key.', ['driver' => $this->name()]);

            return CaptchaResult::fail(CaptchaResult::UNAVAILABLE);
        }

        try {
            $response = Http::asForm()
                ->timeout(config('captcha.http_timeout_seconds'))
                ->post(config("captcha.{$this->configKey()}.verify_url"), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('CAPTCHA provider unreachable.', ['driver' => $this->name(), 'error' => $e->getMessage()]);

            return CaptchaResult::fail(CaptchaResult::UNAVAILABLE);
        }

        if (! $response->successful()) {
            Log::warning('CAPTCHA provider error response.', ['driver' => $this->name(), 'status' => $response->status()]);

            return CaptchaResult::fail(CaptchaResult::UNAVAILABLE);
        }

        $body = $response->json();

        if (! is_array($body) || ($body['success'] ?? false) !== true) {
            $codes = is_array($body) ? ($body['error-codes'] ?? []) : [];

            return CaptchaResult::fail(in_array('timeout-or-duplicate', $codes, true)
                ? CaptchaResult::REPLAYED
                : CaptchaResult::INVALID);
        }

        if (! $this->accepts($body, $purpose)) {
            return CaptchaResult::fail(CaptchaResult::MISMATCH);
        }

        return CaptchaResult::pass();
    }

    public function publicConfig(): array
    {
        return [
            'driver' => $this->name(),
            'site_key' => config("captcha.{$this->configKey()}.site_key"),
        ];
    }

    /** @param array<string, mixed> $response */
    protected function hostnameMatches(array $response): bool
    {
        if (! isset($response['hostname'])) {
            return true;
        }

        $expected = parse_url((string) config('platform.frontend_url'), PHP_URL_HOST);

        return $expected === null || $response['hostname'] === $expected;
    }
}
