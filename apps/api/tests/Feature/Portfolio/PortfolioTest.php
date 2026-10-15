<?php

use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\Models\Page;
use App\Domain\Identity\Enums\Role;
use App\Domain\Media\Actions\DeleteMediaAsset;
use App\Domain\Media\Actions\StoreMediaUpload;
use App\Domain\Portfolio\Models\ExperienceEntry;
use App\Domain\Portfolio\Models\Project;
use App\Domain\Portfolio\Models\Service;
use App\Domain\Portfolio\Models\Skill;
use App\Domain\Portfolio\Models\Testimonial;
use App\Domain\Settings\SettingsRepository;
use App\Filament\Pages\ProfilePage;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Models\User;
use Database\Seeders\InitialContentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function portfolioPage(array $blocks): array
{
    $page = app(SavePageDraft::class)->handle(new Page, ['title' => 'Work', 'slug' => 'work', 'blocks' => $blocks], null);
    app(PublishPage::class)->handle($page, null);

    return test()->withHeaders(test()->apiHeaders())->getJson('/api/v1/pages/work')->assertOk()->json('data.blocks');
}

it('shows only published, featured projects in a featured projects block', function () {
    project();
    project(['slug' => 'draft-one', 'title' => 'Draft', 'status' => 'draft']);
    project(['slug' => 'future', 'title' => 'Future', 'published_at' => now()->addWeek()]);
    project(['slug' => 'not-featured', 'title' => 'Other', 'is_featured' => false]);

    $blocks = portfolioPage([['type' => 'projects', 'data' => ['heading' => 'Work', 'selection' => 'featured', 'limit' => '6']]]);

    expect(collect($blocks[0]['props']['items'])->pluck('slug')->all())->toBe(['sales-dashboard'])
        ->and($blocks[0]['props']['items'][0]['url'])->toBe('/projects/sales-dashboard');
});

it('serves a published case study and hides drafts', function () {
    project(['body' => '<p>Built for <strong>finance</strong>.</p><script>alert(1)</script>', 'links' => [['label' => 'Demo', 'url' => 'https://example.com'], ['label' => 'Bad', 'url' => 'javascript:alert(1)']]]);
    project(['slug' => 'secret', 'status' => 'draft']);

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/projects/sales-dashboard')
        ->assertOk()
        ->assertJsonPath('data.title', 'Sales dashboard')
        ->assertJsonPath('data.bodyHtml', '<p>Built for <strong>finance</strong>.</p>')
        ->assertJsonCount(1, 'data.links');

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/projects/secret')->assertNotFound();
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/page-resolve?path=/projects/secret')->assertJsonPath('data.status', 'missing');
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/page-resolve?path=/projects/sales-dashboard')->assertJsonPath('data.status', 'page');
});

it('lists visible skills by group', function () {
    Skill::query()->create(['group' => 'expertise', 'name' => 'Data Analytics']);
    Skill::query()->create(['group' => 'technology', 'name' => 'Python']);
    Skill::query()->create(['group' => 'technology', 'name' => 'Hidden tool', 'is_visible' => false]);

    $props = portfolioPage([['type' => 'expertise', 'data' => ['heading' => 'Skills']]])[0]['props'];

    expect(collect($props['expertise'])->pluck('name')->all())->toBe(['Data Analytics'])
        ->and(collect($props['technologies'])->pluck('name')->all())->toBe(['Python']);
});

it('only shows testimonials whose authors gave consent', function () {
    Testimonial::query()->create(['author_name' => 'Ada', 'quote' => 'Great course.', 'consent_confirmed_at' => now()]);
    Testimonial::query()->create(['author_name' => 'Bob', 'quote' => 'No consent yet.']);

    $items = portfolioPage([['type' => 'testimonials', 'data' => ['heading' => 'Kind words']]])[0]['props']['items'];

    expect(collect($items)->pluck('author')->all())->toBe(['Ada']);
});

it('returns empty sections when there is nothing to show (no invented content)', function () {
    $blocks = portfolioPage([
        ['type' => 'experience', 'data' => ['heading' => 'Experience']],
        ['type' => 'certifications', 'data' => ['heading' => 'Certifications']],
        ['type' => 'services', 'data' => ['heading' => 'Services']],
    ]);

    foreach ($blocks as $block) {
        expect($block['props']['items'])->toBe([]);
    }
});

it('formats experience periods', function () {
    ExperienceEntry::query()->create(['title' => 'Developer', 'start_date' => '2020-03-01', 'is_current' => true]);

    $items = portfolioPage([['type' => 'experience', 'data' => ['heading' => 'Experience']]])[0]['props']['items'];

    expect($items[0]['period'])->toBe('Mar 2020 – Present');
});

it('uses the editable profile in the profile introduction', function () {
    app(SettingsRepository::class)->set('profile.years_experience', 12);

    $props = portfolioPage([['type' => 'profile_intro', 'data' => ['heading' => 'About', 'show_years' => true]]])[0]['props'];

    expect($props['name'])->toBe('Ifeanyi Osiaku')
        ->and($props['yearsExperience'])->toBe(12)
        ->and($props['shortBio'])->toContain('turning technical knowledge and complex data into practical solutions');
});

it('refreshes published pages when portfolio content or the profile changes', function () {
    portfolioPage([['type' => 'services', 'data' => ['heading' => 'Services']]]);
    Event::fake([PageContentChanged::class]);

    Service::query()->create(['slug' => 'training', 'title' => 'Training']);
    Event::assertDispatched(PageContentChanged::class, fn (PageContentChanged $e) => in_array('/work', $e->paths, true));

    app(SettingsRepository::class)->set('profile.headline', 'New headline');
    Event::assertDispatchedTimes(PageContentChanged::class, 2);
});

it('refreshes both old and new addresses when a project is renamed', function () {
    $project = project();
    Event::fake([PageContentChanged::class]);

    $project->update(['slug' => 'revenue-dashboard']);

    Event::assertDispatched(PageContentChanged::class, fn (PageContentChanged $e) => in_array('/projects/sales-dashboard', $e->paths, true)
        && in_array('/projects/revenue-dashboard', $e->paths, true));
});

it('protects images used by projects from deletion', function () {
    Storage::fake('media', ['url' => config('app.url').'/media']);
    $path = tempnam(sys_get_temp_dir(), 'img');
    imagejpeg(imagecreatetruecolor(800, 500), $path);
    $asset = app(StoreMediaUpload::class)->handle(new UploadedFile($path, 'cover.jpg', null, null, true), null, ['alt_text' => 'Cover']);

    project(['cover_media_id' => $asset->id]);

    expect(fn () => app(DeleteMediaAsset::class)->handle($asset, null))->toThrow(ValidationException::class, 'used in 1 place(s)');
});

it('sanitises project rich text when saving', function () {
    $project = project(['body' => '<p onclick="x()">Hi</p><script>alert(1)</script>']);

    expect($project->fresh()->body)->toBe('<p>Hi</p>');
});

it('lets editors create projects and edit the profile in the admin panel', function () {
    $editor = User::factory()->withTwoFactor()->role(Role::Editor)->create()->fresh();
    $this->actingAs($editor);

    Livewire::test(CreateProject::class)
        ->fillForm(['title' => 'Churn model', 'slug' => 'churn-model', 'status' => 'published', 'published_at' => now()->subMinute()])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Project::query()->where('slug', 'churn-model')->exists())->toBeTrue();

    Livewire::test(ProfilePage::class)
        ->set('data.name', 'Ifeanyi Osiaku')
        ->set('data.years_experience', '13')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('profile.years_experience'))->toBe(13);
});

it('keeps portfolio screens away from staff without the portfolio permission', function () {
    $this->actingAs(User::factory()->withTwoFactor()->role(Role::Moderator)->create()->fresh())->get('/admin/projects')->assertForbidden();
});

it('seeds the approved starting content once, without inventing history', function () {
    Storage::fake('media', ['url' => config('app.url').'/media']);

    $this->seed(InitialContentSeeder::class);
    $this->seed(InitialContentSeeder::class); // safe to re-run

    expect(Skill::query()->count())->toBe(9)
        ->and(Page::query()->where('is_homepage', true)->count())->toBe(1)
        ->and(ExperienceEntry::query()->count())->toBe(0)
        ->and(Testimonial::query()->count())->toBe(0);

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/pages/home')
        ->assertOk()
        ->assertJsonPath('data.blocks.0.type', 'hero')
        ->assertJsonPath('data.blocks.0.props.heading', 'Ifeanyi Osiaku')
        ->assertJsonPath('data.blocks.0.props.media.alt', 'Portrait of Ifeanyi Osiaku');
});
