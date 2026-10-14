<?php

namespace App\Domain\Captcha\Drivers;

use App\Domain\Captcha\CaptchaResult;
use App\Domain\Captcha\Contracts\CaptchaVerifier;
use App\Domain\Captcha\Models\CaptchaChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use JsonException;

/**
 * First-party, accessible CAPTCHA based on a server-signed proof-of-work challenge (ADR-013).
 *
 * Flow: the browser requests a challenge for a form purpose, a Web Worker searches for a nonce so
 * that SHA-256("{challenge}:{nonce}") starts with `difficulty` zero bits, and the form submits
 * "{challenge}:{nonce}". The server checks the HMAC signature, purpose, expiry, session binding,
 * minimum solve time and proof of work, then consumes the challenge exactly once.
 *
 * Token: base64url(json payload) "." base64url(hmac) ":" nonce
 */
class FirstPartyCaptcha implements CaptchaVerifier
{
    public function name(): string
    {
        return 'first_party';
    }

    /**
     * @return array{challenge: string, difficulty: int, algorithm: string, expires_at: string}
     */
    public function issue(string $purpose, Request $request): array
    {
        $config = config('captcha.first_party');
        $difficulty = $config['difficulty'] + $this->elevation($request);
        $id = Str::random(32);
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addSeconds($config['ttl_seconds']);

        CaptchaChallenge::query()->create([
            'id' => $id,
            'purpose' => $purpose,
            'difficulty' => $difficulty,
            'expires_at' => $expiresAt,
            'ip_address' => $request->ip(),
        ]);

        $payload = $this->base64UrlEncode((string) json_encode([
            'id' => $id,
            'p' => $purpose,
            'd' => $difficulty,
            'i' => $issuedAt->getTimestamp(),
            'e' => $expiresAt->getTimestamp(),
            's' => $this->sessionBinding($request),
        ]));

        return [
            'challenge' => $payload.'.'.$this->sign($payload),
            'difficulty' => $difficulty,
            'algorithm' => 'SHA-256',
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function verify(?string $token, string $purpose, Request $request): CaptchaResult
    {
        if (! is_string($token) || $token === '' || strlen($token) > 1024) {
            return CaptchaResult::fail(CaptchaResult::MISSING);
        }

        $separator = strrpos($token, ':');
        if ($separator === false) {
            return CaptchaResult::fail(CaptchaResult::INVALID);
        }

        $challenge = substr($token, 0, $separator);
        $nonce = substr($token, $separator + 1);
        $parts = explode('.', $challenge);

        if (count($parts) !== 2 || $nonce === '' || strlen($nonce) > 32 || ! ctype_alnum($nonce)) {
            return CaptchaResult::fail(CaptchaResult::INVALID);
        }

        [$payload, $signature] = $parts;

        if (! hash_equals($this->sign($payload), $signature)) {
            return CaptchaResult::fail(CaptchaResult::INVALID);
        }

        try {
            $data = json_decode($this->base64UrlDecode($payload), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return CaptchaResult::fail(CaptchaResult::INVALID);
        }

        if (! is_array($data) || ! isset($data['id'], $data['p'], $data['d'], $data['i'], $data['e'])) {
            return CaptchaResult::fail(CaptchaResult::INVALID);
        }

        if ($data['p'] !== $purpose) {
            return CaptchaResult::fail(CaptchaResult::MISMATCH);
        }

        if (now()->getTimestamp() > (int) $data['e']) {
            return CaptchaResult::fail(CaptchaResult::EXPIRED);
        }

        if (($data['s'] ?? null) !== $this->sessionBinding($request)) {
            return CaptchaResult::fail(CaptchaResult::MISMATCH);
        }

        if (now()->getTimestamp() - (int) $data['i'] < config('captcha.first_party.min_solve_seconds')) {
            return CaptchaResult::fail(CaptchaResult::TOO_FAST);
        }

        if (! self::meetsDifficulty($challenge, $nonce, (int) $data['d'])) {
            return CaptchaResult::fail(CaptchaResult::INVALID);
        }

        // Atomic single use: only one request can flip consumed_at from NULL.
        $consumed = CaptchaChallenge::query()
            ->whereKey($data['id'])
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>=', now())
            ->update(['consumed_at' => now()]);

        return $consumed === 1
            ? CaptchaResult::pass()
            : CaptchaResult::fail(CaptchaResult::REPLAYED);
    }

    public function publicConfig(): array
    {
        return [
            'driver' => $this->name(),
            'algorithm' => 'SHA-256',
        ];
    }

    /** Whether SHA-256("{challenge}:{nonce}") starts with at least $bits zero bits. */
    public static function meetsDifficulty(string $challenge, string $nonce, int $bits): bool
    {
        $hash = hash('sha256', $challenge.':'.$nonce, true);
        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        for ($i = 0; $i < $fullBytes; $i++) {
            if ($hash[$i] !== "\0") {
                return false;
            }
        }

        if ($remainingBits === 0) {
            return true;
        }

        return (ord($hash[$fullBytes]) >> (8 - $remainingBits)) === 0;
    }

    private function elevation(Request $request): int
    {
        $config = config('captcha.first_party');
        $key = 'captcha:issued:'.sha1((string) $request->ip());

        Cache::add($key, 0, now()->addMinutes(10));
        $count = (int) Cache::increment($key);

        return $count > $config['elevated_after_challenges'] ? $config['elevated_difficulty_bits'] : 0;
    }

    private function sessionBinding(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        return substr(hash_hmac('sha256', $request->session()->getId(), $this->key()), 0, 16);
    }

    private function sign(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->key(), true));
    }

    private function key(): string
    {
        return hash_hmac('sha256', 'first-party-captcha', (string) config('app.key'), true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
