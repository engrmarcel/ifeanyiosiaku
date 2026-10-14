<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Captcha\Http\VerifyCaptcha;
use App\Models\User;

function login(array $credentials)
{
    return test()->withHeaders(test()->apiHeaders())->postJson('/api/v1/auth/login', $credentials);
}

it('signs in with valid credentials and records the login', function () {
    $user = User::factory()->create(['email' => 'ada@example.test']);

    login(['email' => 'ada@example.test', 'password' => 'password'])->assertOk();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'auth.login')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('treats the email address case-insensitively', function () {
    User::factory()->create(['email' => 'ada@example.test']);

    login(['email' => 'Ada@Example.test', 'password' => 'password'])->assertOk();
    $this->assertAuthenticated();
});

it('rejects a wrong password without revealing whether the account exists', function () {
    User::factory()->create(['email' => 'ada@example.test']);

    $wrongPassword = login(['email' => 'ada@example.test', 'password' => 'wrong-password'])->assertUnprocessable();
    $unknownUser = login(['email' => 'nobody@example.test', 'password' => 'wrong-password'])->assertUnprocessable();

    expect($wrongPassword->json('error.fields.email'))->toBe($unknownUser->json('error.fields.email'));
    $this->assertGuest();
});

it('refuses suspended accounts even with the correct password', function () {
    User::factory()->suspended()->create(['email' => 'ada@example.test']);

    login(['email' => 'ada@example.test', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.email.0', 'This account is not active. Please contact support.');

    $this->assertGuest();
});

it('throttles repeated attempts for the same account', function () {
    User::factory()->create(['email' => 'ada@example.test']);
    config(['captcha.first_party.min_solve_seconds' => 0]);

    for ($i = 0; $i < 5; $i++) {
        $payload = ['email' => 'ada@example.test', 'password' => 'wrong'];
        if ($i >= VerifyCaptcha::LOGIN_FAILURES_BEFORE_CAPTCHA) {
            $payload['captcha_token'] = $this->solveCaptcha('login');
        }
        login($payload)->assertUnprocessable();
    }

    login([
        'email' => 'ada@example.test',
        'password' => 'password',
        'captcha_token' => $this->solveCaptcha('login'),
    ])->assertTooManyRequests();
});

it('requires a CAPTCHA after repeated failures from the same IP', function () {
    User::factory()->create(['email' => 'ada@example.test']);

    for ($i = 0; $i < VerifyCaptcha::LOGIN_FAILURES_BEFORE_CAPTCHA; $i++) {
        login(['email' => 'ada@example.test', 'password' => 'wrong'])->assertUnprocessable();
    }

    login(['email' => 'ada@example.test', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'captcha_failed');

    login([
        'email' => 'ada@example.test',
        'password' => 'password',
        'captcha_token' => $this->solveCaptcha('login'),
    ])->assertOk();
});

it('regenerates the session identifier on login', function () {
    User::factory()->create(['email' => 'ada@example.test']);

    $before = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/csrf-cookie');
    $this->keepSessionFrom($before);
    $after = login(['email' => 'ada@example.test', 'password' => 'password'])->assertOk();

    expect($after->getCookie(config('session.cookie'))->getValue())
        ->not->toBe($before->getCookie(config('session.cookie'))->getValue());
});

it('signs out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/logout')->assertNoContent();
    $this->assertGuest('web');
});

it('rejects state-changing requests without a CSRF token', function () {
    $this->enforceCsrf();
    User::factory()->create(['email' => 'ada@example.test']);

    login(['email' => 'ada@example.test', 'password' => 'password'])
        ->assertStatus(419)
        ->assertJsonPath('error.code', 'csrf_token_mismatch');

    $this->assertGuest();
});
