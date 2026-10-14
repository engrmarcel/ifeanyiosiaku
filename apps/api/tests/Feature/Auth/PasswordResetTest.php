<?php

use App\Domain\Identity\Notifications\ResetPasswordNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('emails a reset link that opens the website reset page', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ada@example.test']);

    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/forgot-password', [
        'email' => 'ada@example.test',
        'captcha_token' => $this->solveCaptcha('password_reset'),
    ])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, config('platform.frontend_url').'/reset-password?token=');
    });
});

it('does not reveal whether an email address is registered', function () {
    Notification::fake();
    User::factory()->create(['email' => 'ada@example.test']);

    $known = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/forgot-password', [
        'email' => 'ada@example.test',
        'captcha_token' => $this->solveCaptcha('password_reset'),
    ])->assertOk();

    $unknown = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/forgot-password', [
        'email' => 'nobody@example.test',
        'captcha_token' => $this->solveCaptcha('password_reset'),
    ])->assertOk();

    expect($unknown->json())->toBe($known->json());
});

it('requires a CAPTCHA to request a reset link', function () {
    User::factory()->create(['email' => 'ada@example.test']);

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/forgot-password', ['email' => 'ada@example.test'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'captcha_failed');
});

it('resets the password with a valid token exactly once', function () {
    $user = User::factory()->create(['email' => 'ada@example.test']);
    $token = Password::broker()->createToken($user);

    $payload = [
        'token' => $token,
        'email' => 'ada@example.test',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ];

    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
    expect(Hash::check('a-brand-new-passphrase', $user->fresh()->password))->toBeTrue();

    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/reset-password', [
        ...$payload,
        'password' => 'another-passphrase-123',
        'password_confirmation' => 'another-passphrase-123',
    ])->assertUnprocessable();
});

it('rejects an expired reset token', function () {
    $user = User::factory()->create(['email' => 'ada@example.test']);
    $token = Password::broker()->createToken($user);
    $this->travel(2)->hours();

    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'ada@example.test',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertUnprocessable();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('signs out every existing session after a reset', function () {
    $user = User::factory()->create(['email' => 'ada@example.test']);
    DB::table('sessions')->insert([
        'id' => str_repeat('a', 40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
    ]);

    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/reset-password', [
        'token' => Password::broker()->createToken($user),
        'email' => 'ada@example.test',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertOk();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});
