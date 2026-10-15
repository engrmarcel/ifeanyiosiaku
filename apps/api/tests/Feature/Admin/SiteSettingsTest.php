<?php

use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\SettingsRepository;
use App\Filament\Pages\SiteSettings;
use App\Models\User;
use Livewire\Livewire;

function settingsStaff(Role $role): User
{
    return User::factory()->withTwoFactor()->role($role)->create()->fresh();
}

it('ships with image saving and Inspect switched off for visitors', function () {
    expect(app(SettingsRepository::class)->bool('protection.allow_saving_and_inspect'))->toBeFalse();

    $data = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/settings/public')->json('data');
    expect($data['protection.allow_saving_and_inspect'])->toBeFalse();
});

it('lets administrators turn content protection off and on, with an audit trail', function () {
    $admin = settingsStaff(Role::Admin);
    $this->actingAs($admin);

    Livewire::test(SiteSettings::class)
        ->assertSet('data.protection__allow_saving_and_inspect', false)
        ->set('data.protection__allow_saving_and_inspect', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->bool('protection.allow_saving_and_inspect'))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'actor_id' => $admin->id]);

    $data = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/settings/public')->json('data');
    expect($data['protection.allow_saving_and_inspect'])->toBeTrue();
});

it('refuses the settings page to staff without the settings permission', function () {
    $this->actingAs(settingsStaff(Role::Editor))->get(SiteSettings::getUrl())->assertForbidden();
});

it('renders for administrators', function () {
    $this->actingAs(settingsStaff(Role::Admin))->get(SiteSettings::getUrl())
        ->assertOk()
        ->assertSee('Allow visitors to save images and use Inspect');
});

it('reserves security settings for super administrators', function () {
    $this->actingAs(settingsStaff(Role::Admin));

    Livewire::test(SiteSettings::class)
        ->set('data.security__require_staff_two_factor', false)
        ->call('save');

    expect(app(SettingsRepository::class)->bool('security.require_staff_two_factor'))->toBeTrue();
});

it('only offers CAPTCHA providers whose keys are configured', function () {
    $this->actingAs(settingsStaff(Role::SuperAdmin));

    Livewire::test(SiteSettings::class)
        ->set('data.captcha__driver', 'turnstile')
        ->call('save');

    expect(app(SettingsRepository::class)->get('captcha.driver'))->toBe('first_party');

    config(['captcha.turnstile.site_key' => 'site', 'captcha.turnstile.secret_key' => 'secret']);

    Livewire::test(SiteSettings::class)
        ->set('data.captcha__driver', 'turnstile')
        ->call('save');

    expect(app(SettingsRepository::class)->get('captcha.driver'))->toBe('turnstile');
});
