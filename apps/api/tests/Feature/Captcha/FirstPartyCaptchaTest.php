<?php

use App\Domain\Captcha\Drivers\FirstPartyCaptcha;
use App\Domain\Captcha\Models\CaptchaChallenge;

function submitContact(string $token, array $extra = [])
{
    // Registration is the most important protected form; its route is the captcha enforcement point.
    return test()->withHeaders(test()->apiHeaders())->postJson('/api/v1/auth/register', [
        'name' => 'Ada Learner',
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'captcha_token' => $token,
        ...$extra,
    ]);
}

it('issues a signed challenge and records it for single use', function () {
    $response = $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/captcha/challenge', ['purpose' => 'register'])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['challenge', 'difficulty', 'algorithm', 'expires_at']]);

    expect($response->json('data.algorithm'))->toBe('SHA-256')
        ->and(CaptchaChallenge::query()->count())->toBe(1);
});

it('rejects unknown purposes', function () {
    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/captcha/challenge', ['purpose' => 'anything'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');
});

it('accepts a correctly solved challenge', function () {
    submitContact($this->solveCaptcha('register'))->assertCreated();
});

it('rejects a missing token', function () {
    submitContact('')->assertUnprocessable()
        ->assertJsonPath('error.code', 'captcha_failed')
        ->assertJsonPath('error.reason', 'missing');
});

it('rejects a token with a forged signature', function () {
    [$challenge, $nonce] = explode(':', $this->solveCaptcha('register'));
    [$payload] = explode('.', $challenge);

    submitContact($payload.'.forgedsignature:'.$nonce)
        ->assertUnprocessable()
        ->assertJsonPath('error.reason', 'invalid');
});

it('rejects an insufficient proof of work', function () {
    $token = $this->solveCaptcha('register');
    [$challenge] = explode(':', $token);

    $badNonce = 0;
    while (FirstPartyCaptcha::meetsDifficulty($challenge, dechex($badNonce), 8)) {
        $badNonce++;
    }

    submitContact($challenge.':'.dechex($badNonce))
        ->assertUnprocessable()
        ->assertJsonPath('error.reason', 'invalid');
});

it('rejects an expired challenge', function () {
    $token = $this->solveCaptcha('register');
    $this->travel(10)->minutes();

    submitContact($token)->assertUnprocessable()->assertJsonPath('error.reason', 'expired');
});

it('rejects a replayed challenge', function () {
    $token = $this->solveCaptcha('register');

    submitContact($token)->assertCreated();
    auth()->guard('web')->logout();

    submitContact($token, ['email' => 'second@example.test'])
        ->assertUnprocessable()
        ->assertJsonPath('error.reason', 'replayed');
});

it('rejects a challenge issued for another form', function () {
    submitContact($this->solveCaptcha('contact'))
        ->assertUnprocessable()
        ->assertJsonPath('error.reason', 'mismatch');
});

it('rejects a challenge bound to another session', function () {
    $token = $this->solveCaptcha('register');
    $this->withCookie(config('session.cookie'), 'a-different-session-id-000000000000000000');

    submitContact($token)->assertUnprocessable()->assertJsonPath('error.reason', 'mismatch');
});

it('rejects submissions faster than a human could solve', function () {
    config(['captcha.first_party.min_solve_seconds' => 30]);

    submitContact($this->solveCaptcha('register'))
        ->assertUnprocessable()
        ->assertJsonPath('error.reason', 'too_fast');
});

it('rejects submissions that fill the honeypot field', function () {
    submitContact($this->solveCaptcha('register'), ['website_url' => 'http://spam.example'])
        ->assertUnprocessable()
        ->assertJsonPath('error.reason', 'honeypot');
});

it('rate limits challenge issuance per IP', function () {
    for ($i = 0; $i < 20; $i++) {
        $this->withHeaders($this->apiHeaders())->postJson('/api/v1/captcha/challenge', ['purpose' => 'register'])->assertCreated();
    }

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/captcha/challenge', ['purpose' => 'register'])
        ->assertTooManyRequests()
        ->assertJsonPath('error.code', 'too_many_requests');
});

it('raises difficulty for IPs requesting many challenges', function () {
    $base = config('captcha.first_party.difficulty');
    $last = null;

    for ($i = 0; $i < 12; $i++) {
        $last = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/captcha/challenge', ['purpose' => 'register']);
    }

    expect($last->json('data.difficulty'))->toBe($base + config('captcha.first_party.elevated_difficulty_bits'));
});

it('counts leading zero bits correctly', function () {
    // Brute-force a nonce at 4 bits and verify it does not accidentally satisfy a much higher target.
    $nonce = 0;
    while (! FirstPartyCaptcha::meetsDifficulty('abc', dechex($nonce), 4)) {
        $nonce++;
    }

    $hash = hash('sha256', 'abc:'.dechex($nonce));
    expect(hexdec($hash[0]))->toBe(0);
});
