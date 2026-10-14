<?php

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a verified super administrator with an interactively entered password', function () {
    $this->artisan('app:create-super-admin', ['--email' => 'owner@example.test', '--name' => 'Ifeanyi Osiaku'])
        ->expectsQuestion('Password (min. 10 characters)', 'a-strong-passphrase')
        ->expectsQuestion('Confirm password', 'a-strong-passphrase')
        ->assertSuccessful();

    $user = User::query()->where('email', 'owner@example.test')->firstOrFail();

    expect($user->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('a-strong-passphrase', $user->password))->toBeTrue()
        ->and($user->profile)->not->toBeNull();
});

it('rejects weak or mismatched passwords', function () {
    $this->artisan('app:create-super-admin', ['--email' => 'owner@example.test', '--name' => 'Owner'])
        ->expectsQuestion('Password (min. 10 characters)', 'short')
        ->expectsQuestion('Confirm password', 'different')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('promotes an existing account without changing its password', function () {
    $user = User::factory()->create(['email' => 'owner@example.test']);

    $this->artisan('app:create-super-admin', ['--email' => 'owner@example.test'])->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('grants super administrators every permission', function () {
    $user = User::factory()->role(Role::SuperAdmin)->create();

    foreach (Permission::cases() as $permission) {
        expect($user->can($permission->value))->toBeTrue();
    }
});
