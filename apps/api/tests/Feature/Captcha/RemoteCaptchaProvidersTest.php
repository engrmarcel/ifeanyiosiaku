<?php

use App\Domain\Captcha\CaptchaManager;
use App\Domain\Settings\SettingsRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function verifyWith(string $driver, ?string $token = 'browser-token', string $purpose = 'register')
{
    return app(CaptchaManager::class)->driver($driver)->verify($token, $purpose, Request::create('/', 'POST'));
}

beforeEach(function () {
    config([
        'captcha.recaptcha.secret_key' => 'test-recaptcha-secret',
        'captcha.turnstile.secret_key' => 'test-turnstile-secret',
    ]);
});

it('verifies reCAPTCHA tokens server-side with the secret key', function () {
    Http::fake(['www.google.com/*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'register', 'hostname' => 'localhost'])]);

    expect(verifyWith('recaptcha')->passed)->toBeTrue();

    Http::assertSent(fn ($request) => $request['secret'] === 'test-recaptcha-secret' && $request['response'] === 'browser-token');
});

it('rejects reCAPTCHA tokens the provider marks invalid', function () {
    Http::fake(['www.google.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    expect(verifyWith('recaptcha')->reason)->toBe('invalid');
});

it('rejects replayed reCAPTCHA tokens', function () {
    Http::fake(['www.google.com/*' => Http::response(['success' => false, 'error-codes' => ['timeout-or-duplicate']])]);

    expect(verifyWith('recaptcha')->reason)->toBe('replayed');
});

it('rejects low reCAPTCHA scores and mismatched actions', function () {
    Http::fake(['www.google.com/*' => Http::sequence()
        ->push(['success' => true, 'score' => 0.1, 'action' => 'register'])
        ->push(['success' => true, 'score' => 0.9, 'action' => 'login'])]);

    expect(verifyWith('recaptcha')->reason)->toBe('mismatch')
        ->and(verifyWith('recaptcha')->reason)->toBe('mismatch');
});

it('rejects tokens issued for a different hostname', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'hostname' => 'evil.example'])]);

    expect(verifyWith('turnstile')->reason)->toBe('mismatch');
});

it('verifies Turnstile tokens', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
        ->push(['success' => true, 'action' => 'register', 'hostname' => 'localhost'])
        ->push(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    expect(verifyWith('turnstile')->passed)->toBeTrue()
        ->and(verifyWith('turnstile')->reason)->toBe('invalid');
});

it('fails closed when the provider is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect(verifyWith('recaptcha')->reason)->toBe('unavailable')
        ->and(verifyWith('turnstile')->reason)->toBe('unavailable');
});

it('fails closed when the provider returns a server error', function () {
    Http::fake(['*' => Http::response('down', 500)]);

    expect(verifyWith('turnstile')->reason)->toBe('unavailable');
});

it('fails closed when the secret key is not configured', function () {
    config(['captcha.recaptcha.secret_key' => null]);
    Http::fake();

    expect(verifyWith('recaptcha')->reason)->toBe('unavailable');
    Http::assertNothingSent();
});

it('never trusts a browser-supplied success flag', function () {
    app(SettingsRepository::class)->set('captcha.driver', 'turnstile');
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/register', [
        'name' => 'Bot',
        'email' => 'bot@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'captcha_token' => 'anything',
        'captcha_success' => true,
    ])->assertUnprocessable()->assertJsonPath('error.code', 'captcha_failed');
});

it('returns 503 to the form when verification is unavailable', function () {
    app(SettingsRepository::class)->set('captcha.driver', 'recaptcha');
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/register', [
        'name' => 'Ada', 'email' => 'ada@example.test',
        'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery',
        'captcha_token' => 'token',
    ])->assertStatus(503)->assertJsonPath('error.code', 'captcha_unavailable');
});

it('exposes only public provider configuration', function () {
    config(['captcha.turnstile.site_key' => 'public-site-key']);
    app(SettingsRepository::class)->set('captcha.driver', 'turnstile');

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/captcha/config')
        ->assertOk()
        ->assertJsonPath('data.driver', 'turnstile')
        ->assertJsonPath('data.site_key', 'public-site-key')
        ->assertJsonMissing(['test-turnstile-secret']);
});
