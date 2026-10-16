<?php

use App\Domain\Announcements\Models\Announcement;
use App\Domain\Announcements\Models\AnnouncementEvent;
use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\SettingsRepository;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\User;
use Livewire\Livewire;

function announcement(array $overrides = []): Announcement
{
    return Announcement::query()->create([
        'title' => 'New course launching',
        'message' => 'Power BI from zero to dashboards.',
        'cta_label' => 'Learn more',
        'cta_url' => '/courses',
        'is_active' => true,
        ...$overrides,
    ]);
}

function currentAnnouncement(string $path = '/')
{
    return test()->withHeaders(test()->apiHeaders())->getJson('/api/v1/announcements/current?path='.urlencode($path))->assertOk();
}

it('shows nothing while the global switch is off, even for active scheduled announcements', function () {
    announcement(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

    expect(app(SettingsRepository::class)->bool('announcements.enabled'))->toBeFalse(); // OFF by default
    currentAnnouncement()->assertExactJson(['data' => null]);
});

it('shows the highest-priority running announcement once switched on', function () {
    app(SettingsRepository::class)->set('announcements.enabled', true);
    announcement(['title' => 'Low']);
    $high = announcement(['title' => 'High', 'priority' => 10]);
    announcement(['title' => 'Inactive', 'priority' => 99, 'is_active' => false]);
    announcement(['title' => 'Future', 'priority' => 99, 'starts_at' => now()->addDay()]);
    announcement(['title' => 'Expired', 'priority' => 99, 'ends_at' => now()->subMinute()]);

    currentAnnouncement()
        ->assertJsonPath('data.id', $high->ulid)
        ->assertJsonPath('data.cta', ['label' => 'Learn more', 'url' => '/courses'])
        ->assertJsonPath('data.frequency', 'once')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('respects page placement and audience', function () {
    app(SettingsRepository::class)->set('announcements.enabled', true);
    announcement(['title' => 'Home only', 'placement' => 'home']);
    announcement(['title' => 'Members', 'audience' => 'members', 'priority' => 5]);

    currentAnnouncement('/')->assertJsonPath('data.title', 'Home only');
    currentAnnouncement('/articles')->assertExactJson(['data' => null]);

    $this->actingAs(User::factory()->create());
    currentAnnouncement('/articles')->assertJsonPath('data.title', 'Members');
});

it('records impressions, clicks and dismissals without double-counting reloads', function () {
    app(SettingsRepository::class)->set('announcements.enabled', true);
    $a = announcement();
    $send = fn (string $type, string $visitor = 'visitor-abcdefghijklmnop') => $this->withHeaders($this->apiHeaders())
        ->postJson("/api/v1/announcements/{$a->ulid}/events", ['type' => $type, 'visitor' => $visitor]);

    $send('impression')->assertNoContent();
    $send('impression')->assertNoContent(); // same browser, within 30 minutes
    $send('impression', 'other-visitor-123456789')->assertNoContent();
    $send('click')->assertNoContent();
    $send('dismiss')->assertNoContent();
    $send('hack')->assertUnprocessable();

    expect(AnnouncementEvent::query()->where('type', 'impression')->count())->toBe(2)
        ->and(AnnouncementEvent::query()->where('type', 'click')->count())->toBe(1)
        ->and(AnnouncementEvent::query()->whereNotNull('visitor_hash')->value('visitor_hash'))->not->toContain('visitor');

    app(SettingsRepository::class)->set('announcements.enabled', false);
    $send('click')->assertNotFound();
});

it('lets publishers manage announcements and warns when the popup is switched off', function () {
    $this->actingAs(contentStaff());

    Livewire::test(ManageAnnouncements::class)
        ->assertSee('switched OFF')
        ->callAction('create', [
            'title' => 'Webinar', 'message' => 'Join us live.', 'audience' => 'everyone', 'placement' => 'all',
            'frequency' => 'session', 'priority' => 1, 'is_active' => true,
            'cta_label' => 'Register', 'cta_url' => 'javascript:alert(1)',
        ])
        ->assertHasFormErrors(['cta_url']);

    Livewire::test(ManageAnnouncements::class)
        ->callAction('create', [
            'title' => 'Webinar', 'message' => 'Join us live.', 'audience' => 'everyone', 'placement' => 'all',
            'frequency' => 'session', 'priority' => 1, 'is_active' => true, 'cta_label' => 'Register', 'cta_url' => 'https://example.com/webinar',
        ])
        ->assertHasNoFormErrors();

    expect(Announcement::query()->where('title', 'Webinar')->value('frequency'))->toBe('session');
    $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.created']);
});

it('hides announcements from staff without content permissions', function () {
    $this->actingAs(contentStaff(Role::Moderator))->get('/admin/announcements')->assertForbidden();
});
