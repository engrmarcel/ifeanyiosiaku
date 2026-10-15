<?php

use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Models\Page;
use App\Domain\Settings\SettingsRepository;

function sitemapPaths(): array
{
    return collect(test()->withHeaders(test()->apiHeaders())->getJson('/api/v1/sitemap')->assertOk()->json('data.entries'))
        ->pluck('path')->all();
}

it('lists published, indexable pages and case studies', function () {
    livePage('about');
    app(PublishPage::class)->handle(app(SavePageDraft::class)->handle(new Page, [
        'title' => 'Hidden', 'slug' => 'hidden', 'blocks' => [], 'seo' => ['noindex' => true],
    ], null), null);
    app(PublishPage::class)->handle(app(SavePageDraft::class)->handle(new Page, [
        'title' => 'Copy', 'slug' => 'copy', 'blocks' => [], 'seo' => ['canonical_url' => 'https://elsewhere.example/original'],
    ], null), null);
    app(SavePageDraft::class)->handle(new Page, ['title' => 'Draft', 'slug' => 'draft', 'blocks' => []], null);
    project();
    project(['slug' => 'unpublished', 'status' => 'draft', 'published_at' => null]);

    expect(sitemapPaths())->toBe(['/about', '/projects/sales-dashboard']);
});

it('updates when content is published', function () {
    expect(sitemapPaths())->toBe([]);

    livePage('services');

    expect(sitemapPaths())->toBe(['/services']);
});

it('is empty while search-engine indexing is switched off', function () {
    livePage('about');
    expect(sitemapPaths())->toBe(['/about']);

    app(SettingsRepository::class)->set('seo.allow_indexing', false);

    expect(sitemapPaths())->toBe([]);
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/site')->assertJsonPath('data.indexing', false);
});
