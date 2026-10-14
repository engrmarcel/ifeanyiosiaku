<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Notifications\VerifyEmailNotification;
use App\Domain\Settings\SettingsRepository;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Ada Learner',
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        ...$overrides,
    ];
}

it('registers a member with a profile, member role and verification email', function () {
    Notification::fake();

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/register', registrationPayload(['captcha_token' => $this->solveCaptcha('register')]))
        ->assertCreated();

    $user = User::query()->where('email', 'ada@example.test')->firstOrFail();

    expect($user->hasRole(Role::Member->value))->toBeTrue()
        ->and($user->profile->display_name)->toBe('Ada Learner')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->ulid)->toHaveLength(26)
        ->and(AuditLog::query()->where('action', 'auth.registered')->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('requires a CAPTCHA to register', function () {
    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/register', registrationPayload())
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'captcha_failed');

    expect(User::query()->count())->toBe(0);
});

it('validates registration input', function (array $overrides, string $field) {
    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/register', registrationPayload([...$overrides, 'captcha_token' => $this->solveCaptcha('register')]))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => [$field]]]);
})->with([
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'something-else-entirely'], 'password'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'missing name' => [['name' => ''], 'name'],
    'invalid timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
]);

it('rejects duplicate email addresses regardless of case', function () {
    User::factory()->create(['email' => 'ada@example.test']);

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/register', registrationPayload([
            'email' => 'ADA@example.test',
            'captcha_token' => $this->solveCaptcha('register'),
        ]))
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['fields' => ['email']]]);
});

it('refuses registration when an administrator closes it', function () {
    app(SettingsRepository::class)->set('registration.enabled', false);

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/register', registrationPayload(['captcha_token' => $this->solveCaptcha('register')]))
        ->assertUnprocessable();

    expect(User::query()->count())->toBe(0);
});
