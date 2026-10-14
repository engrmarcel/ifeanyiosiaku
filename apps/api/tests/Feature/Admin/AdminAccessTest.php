<?php

use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\SettingsRepository;
use App\Models\User;

it('sends signed-out visitors to the website login page', function () {
    $this->get('/admin')->assertRedirect(config('platform.frontend_url').'/login?next=%2Fadmin');
});

it('refuses members', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create()->assignRole(Role::Member->value))
        ->get('/admin')
        ->assertForbidden();
});

it('sends staff without two-factor authentication to set it up', function () {
    $this->actingAs(User::factory()->role(Role::Admin)->create())
        ->get('/admin')
        ->assertRedirect(config('platform.frontend_url').'/account/security?setup=two-factor&next=/admin');
});

it('admits staff with two-factor authentication', function () {
    $this->actingAs(User::factory()->withTwoFactor()->role(Role::Admin)->create())
        ->get('/admin')
        ->assertOk();
});

it('can relax the two-factor requirement only through settings', function () {
    app(SettingsRepository::class)->set('security.require_staff_two_factor', false);

    $this->actingAs(User::factory()->role(Role::Editor)->create())->get('/admin')->assertOk();
});

it('refuses staff with an unverified email address', function () {
    $this->actingAs(User::factory()->unverified()->withTwoFactor()->role(Role::Admin)->create())
        ->get('/admin')
        ->assertForbidden();
});

it('removes access immediately when the staff role is revoked', function () {
    $staff = User::factory()->withTwoFactor()->role(Role::Admin)->create();
    $this->actingAs($staff)->get('/admin')->assertOk();

    $staff->syncRoles([Role::Member->value]);

    $this->actingAs($staff->fresh())->get('/admin')->assertForbidden();
});

it('logs out suspended staff from the admin panel', function () {
    $staff = User::factory()->withTwoFactor()->role(Role::Admin)->suspended()->create();

    $this->actingAs($staff)->get('/admin')
        ->assertRedirect(config('platform.frontend_url').'/login?reason=account_inactive');

    $this->assertGuest('web');
});
