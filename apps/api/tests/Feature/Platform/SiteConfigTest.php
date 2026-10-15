<?php

use App\Domain\Cms\Jobs\RefreshWebsitePaths;
use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\SettingsRepository;
use App\Filament\Pages\NavigationPage;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function siteConfig()
{
    return test()->withHeaders(test()->apiHeaders())->getJson('/api/v1/site')->assertOk();
}

it('serves the default navigation, theme and identity', function () {
    siteConfig()
        ->assertJsonPath('data.name', 'Ifeanyi Osiaku')
        ->assertJsonPath('data.navigation.header.0', ['label' => 'About', 'url' => '/#about', 'newTab' => false])
        ->assertJsonCount(3, 'data.navigation.header')
        ->assertJsonPath('data.navigation.footer', [])
        ->assertJsonPath('data.social', [])
        ->assertJsonPath('data.theme.accent', 'green')
        ->assertJsonPath('data.theme.tokens.accent', '#a3f7bf')
        ->assertJsonPath('data.indexing', true)
        ->assertJsonPath('data.profile.headline', config('platform.defaults')['profile.headline']);
});

it('hides links belonging to a switched-off add-on and drops unsafe links', function () {
    $settings = app(SettingsRepository::class);
    $settings->set('navigation.header', [
        ['label' => 'Store', 'url' => '/store', 'addon' => 'commerce'],
        ['label' => 'Bad', 'url' => 'javascript:alert(1)'],
        ['label' => '', 'url' => '/empty-label'],
        ['label' => 'Courses', 'url' => '/courses', 'addon' => 'learning'],
    ]);
    $settings->set('addons.commerce.enabled', false);

    siteConfig()->assertJsonPath('data.navigation.header', [['label' => 'Courses', 'url' => '/courses', 'newTab' => false]]);
});

it('saves navigation, social profiles and accent from the admin and refreshes the website', function () {
    Queue::fake();
    $this->actingAs(contentStaff());

    Livewire::test(NavigationPage::class)
        ->fillForm([
            'header' => [['label' => 'Work', 'url' => '/#work', 'new_tab' => false, 'addon' => null]],
            'footer' => [['label' => 'Privacy', 'url' => '/privacy', 'new_tab' => false, 'addon' => null]],
            'social' => [
                ['network' => 'github', 'url' => 'https://github.com/example'],
                ['network' => 'email', 'url' => 'hello@example.com'],
            ],
            'footer_note' => 'Data · AI',
            'accent' => 'cyan',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    siteConfig()
        ->assertJsonPath('data.navigation.header', [['label' => 'Work', 'url' => '/#work', 'newTab' => false]])
        ->assertJsonPath('data.navigation.footer.0.url', '/privacy')
        ->assertJsonPath('data.social.0', ['network' => 'github', 'label' => 'GitHub', 'url' => 'https://github.com/example'])
        ->assertJsonPath('data.social.1.url', 'mailto:hello@example.com')
        ->assertJsonPath('data.footerNote', 'Data · AI')
        ->assertJsonPath('data.theme.accent', 'cyan');

    Queue::assertPushed(RefreshWebsitePaths::class, fn (RefreshWebsitePaths $job) => $job->tags === ['site']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
});

it('rejects unsafe links and invalid social profiles in the admin', function () {
    $this->actingAs(contentStaff());

    Livewire::test(NavigationPage::class)
        ->fillForm([
            'header' => [['label' => 'Bad', 'url' => 'javascript:alert(1)', 'new_tab' => false, 'addon' => null]],
            'social' => [['network' => 'linkedin', 'url' => 'http://linkedin.com/in/x']],
            'accent' => 'green',
        ])
        ->call('save')
        ->assertHasFormErrors();

    expect(app(SettingsRepository::class)->get('navigation.header'))->toHaveCount(3);
});

it('limits the navigation page to content staff', function () {
    $this->actingAs(contentStaff(Role::Moderator))->get('/admin/navigation')->assertForbidden();
    $this->actingAs(contentStaff())->get('/admin/navigation')->assertOk();
});
