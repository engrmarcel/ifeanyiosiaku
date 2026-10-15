<?php

use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\Addons;
use App\Domain\Settings\Enums\Addon;
use App\Domain\Settings\SettingsRepository;
use App\Filament\Pages\SiteSettings;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    // Stand-in routes until the real modules exist.
    foreach (Addon::cases() as $addon) {
        Route::middleware(['web', "addon:{$addon->value}"])
            ->get("/api/v1/_addon-probe/{$addon->value}", fn () => response()->json(['ok' => true]));
    }
});

it('has every add-on module switched on by default', function () {
    expect(app(Addons::class)->all())->toBe([
        'commerce' => true,
        'learning' => true,
        'community' => true,
        'rewards' => true,
    ]);

    $public = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/settings/public')->json('data');
    foreach (Addon::cases() as $addon) {
        expect($public[$addon->settingKey()])->toBeTrue();
    }
});

it('lets requests through to an enabled module', function (Addon $addon) {
    $this->withHeaders($this->apiHeaders())->getJson("/api/v1/_addon-probe/{$addon->value}")->assertOk();
})->with(Addon::cases());

it('hides a switched-off module as if it did not exist', function (Addon $addon) {
    app(SettingsRepository::class)->set($addon->settingKey(), false);

    $this->withHeaders($this->apiHeaders())->getJson("/api/v1/_addon-probe/{$addon->value}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'addon_disabled');

    // Other modules are unaffected.
    $other = collect(Addon::cases())->first(fn (Addon $a) => $a !== $addon);
    $this->withHeaders($this->apiHeaders())->getJson("/api/v1/_addon-probe/{$other->value}")->assertOk();
})->with(Addon::cases());

it('switches modules from the admin settings page with an audit trail', function () {
    $admin = User::factory()->withTwoFactor()->role(Role::Admin)->create()->fresh();
    $this->actingAs($admin);

    Livewire::test(SiteSettings::class)
        ->assertSet('data.addons__community__enabled', true)
        ->set('data.addons__community__enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(Addons::class)->enabled(Addon::Community))->toBeFalse()
        ->and(app(Addons::class)->enabled(Addon::Commerce))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'actor_id' => $admin->id]);

    $public = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/settings/public')->json('data');
    expect($public['addons.community.enabled'])->toBeFalse();
});
