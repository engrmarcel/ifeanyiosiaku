<?php

use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use Livewire\Livewire;

/*
| Journey F (PRODUCT_REQUIREMENTS §4.2 acceptance): an administrator creates a page, adds and
| reorders blocks, hides and duplicates one, saves a draft, previews it, publishes it and sees it
| on the public site with the right metadata — all through the admin screens, without code.
*/

it('completes Journey F: create, arrange, preview and publish a page from the admin', function () {
    $this->actingAs(contentStaff());

    $heading = ['type' => 'heading', 'data' => ['text' => 'Power BI training', 'level' => '2', 'align' => 'left', 'visible' => true]];
    $text = ['type' => 'rich_text', 'data' => ['body' => '<p>Hands-on workshops for analysts.</p>', 'width' => 'narrow', 'visible' => true]];
    $cta = ['type' => 'cta', 'data' => ['heading' => 'Book a workshop', 'primary_label' => 'Contact me', 'primary_url' => '/contact', 'visible' => true]];

    // 1. Create the page with three blocks and search-engine details.
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Training',
            'slug' => 'training',
            'template' => 'default',
            'seo' => ['title' => 'Power BI & DAX training', 'description' => 'Workshops in Power BI, DAX and data modelling.', 'noindex' => false],
            'blocks' => builderState([$heading, $text, $cta]),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::query()->where('slug', 'training')->firstOrFail();
    expect($page->status)->toBe(PageStatus::Draft);

    // 2. Saved drafts are invisible to visitors.
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/pages/training')->assertNotFound();
    resolvePath('/training')->assertJsonPath('data.status', 'missing');

    // 3. Reorder (call to action first), hide the text block and duplicate the heading.
    $hiddenText = ['type' => 'rich_text', 'data' => [...$text['data'], 'visible' => false]];
    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->set('data.blocks', builderState([$cta, $heading, $hiddenText, $heading]))
        ->call('save')
        ->assertHasNoFormErrors();

    // 4. The preview link shows the saved draft, in its new order, without the hidden block.
    $url = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->instance()->getAction('preview')->getUrl();
    $token = urldecode(substr((string) $url, strpos((string) $url, 'token=') + 6));
    $preview = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/preview/page?token='.urlencode($token))->assertOk();

    expect(array_column($preview->json('data.blocks'), 'type'))->toBe(['cta', 'heading', 'heading'])
        ->and($preview->json('data.seo.noindex'))->toBeTrue(); // previews are never indexed

    // 5. Publish.
    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Published — the page is live');

    // 6. Visitors see it, in the arranged order, with the page's own metadata, and it is listed for search engines.
    $public = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/pages/training')->assertOk();

    expect(array_column($public->json('data.blocks'), 'type'))->toBe(['cta', 'heading', 'heading'])
        ->and($public->json('data.blocks.1.props.text'))->toBe('Power BI training')
        ->and($public->json('data.seo'))->toMatchArray([
            'title' => 'Power BI & DAX training',
            'description' => 'Workshops in Power BI, DAX and data modelling.',
            'noindex' => false,
        ])
        ->and($public->json('data.path'))->toBe('/training');

    resolvePath('/training')->assertJsonPath('data.status', 'page');
    expect(sitemapPaths())->toContain('/training');
});
