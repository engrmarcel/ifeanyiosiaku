<?php

use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use App\Domain\Identity\Enums\Role;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use Livewire\Livewire;

it('creates a page draft with blocks from the admin panel', function () {
    $this->actingAs(contentStaff());

    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Services',
            'slug' => 'services',
            'template' => 'default',
            'blocks' => builderState([
                ['type' => 'heading', 'data' => ['text' => 'What I do', 'level' => '2', 'align' => 'left', 'visible' => true]],
                ['type' => 'stats', 'data' => ['items' => [['value' => '12+', 'label' => 'Years of experience']], 'visible' => true]],
            ]),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::query()->where('slug', 'services')->firstOrFail();
    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->blocks)->toHaveCount(2)
        ->and($page->blocks[1]['data']['items'][0]['value'])->toBe('12+');
});

it('shows block validation errors next to the field', function () {
    $this->actingAs(contentStaff());

    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Bad link',
            'slug' => 'bad-link',
            'blocks' => builderState([
                ['type' => 'cta', 'data' => ['heading' => 'Book', 'primary_label' => 'Go', 'primary_url' => 'javascript:alert(1)', 'visible' => true]],
            ]),
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Page::query()->where('slug', 'bad-link')->exists())->toBeFalse();
});

it('publishes from the editor and keeps later edits as a draft', function () {
    $this->actingAs(contentStaff());

    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Contact', 'slug' => 'contact', 'blocks' => builderState([
            ['type' => 'heading', 'data' => ['text' => 'Say hello', 'visible' => true]],
        ])])
        ->call('create');

    $page = Page::query()->where('slug', 'contact')->firstOrFail();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Published — the page is live');

    expect($page->fresh()->status)->toBe(PageStatus::Published);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['title' => 'Contact (edited)'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/pages/contact')->assertJsonPath('data.title', 'Contact');
});

it('hides the publish button from staff without the publish permission', function () {
    $page = Page::query()->create(['title' => 'Draft', 'slug' => 'draft-only', 'blocks' => []]);
    $instructorWithCms = contentStaff(Role::Instructor);
    $instructorWithCms->givePermissionTo('cms.manage');

    $this->actingAs($instructorWithCms);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->assertActionHidden('publish');
});

it('offers a preview link that opens the draft on the website', function () {
    $this->actingAs(contentStaff());
    $page = Page::query()->create(['title' => 'Preview me', 'slug' => 'preview-me', 'blocks' => []]);

    $url = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->instance()->getAction('preview')->getUrl();

    expect($url)->toStartWith(config('platform.frontend_url').'/preview?token=');

    $token = urldecode(substr($url, strpos($url, 'token=') + 6));
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/preview/page?token='.urlencode($token))
        ->assertOk()
        ->assertJsonPath('data.title', 'Preview me');
});
