<?php

use App\Models\User;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

function currentCode(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($user->two_factor_secret));
}

it('asks for a second factor when the account has two-factor enabled', function () {
    User::factory()->withTwoFactor()->create(['email' => 'ada@example.test']);

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/login', ['email' => 'ada@example.test', 'password' => 'password'])
        ->assertOk()
        ->assertJson(['two_factor' => true]);

    $this->assertGuest();
});

it('completes sign-in with a valid authenticator code', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'ada@example.test']);

    $first = $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/login', ['email' => 'ada@example.test', 'password' => 'password']);
    $this->keepSessionFrom($first);

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/two-factor-challenge', ['code' => currentCode($user)])
        ->assertNoContent();

    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid authenticator code', function () {
    User::factory()->withTwoFactor()->create(['email' => 'ada@example.test']);

    $first = $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/login', ['email' => 'ada@example.test', 'password' => 'password']);
    $this->keepSessionFrom($first);

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])
        ->assertUnprocessable();

    $this->assertGuest();
});

it('accepts a one-time recovery code', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'ada@example.test']);

    $first = $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/login', ['email' => 'ada@example.test', 'password' => 'password']);
    $this->keepSessionFrom($first);

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])
        ->assertNoContent();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->recoveryCodes())->not->toContain('recovery-code-1');
});

it('enables two-factor only after password confirmation and a confirming code', function () {
    $user = User::factory()->create()->fresh();

    $this->actingAs($user)->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/user/two-factor-authentication')
        ->assertStatus(423); // password confirmation required

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->postJson('/api/v1/auth/user/two-factor-authentication')
        ->assertOk();

    $user->refresh();
    expect($user->hasConfirmedTwoFactor())->toBeFalse();

    $this->postJson('/api/v1/auth/user/confirmed-two-factor-authentication', ['code' => currentCode($user)])
        ->assertOk();

    expect($user->fresh()->hasConfirmedTwoFactor())->toBeTrue();
});
