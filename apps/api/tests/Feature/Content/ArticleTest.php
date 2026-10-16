<?php

use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Jobs\RefreshWebsitePaths;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\PreviewToken;
use App\Domain\Content\Console\AnnounceScheduledContent;
use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Tag;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Domain\Media\Actions\DeleteMediaAsset;
use App\Domain\Media\Actions\StoreMediaUpload;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Taxonomy\CategoryResource;
use App\Filament\Resources\Taxonomy\TagResource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function article(array $overrides = []): Article
{
    return Article::query()->create([
        'slug' => 'power-bi-measures',
        'title' => 'Writing Power BI measures',
        'excerpt' => 'DAX patterns that stay fast.',
        'body' => "## Why measures\n\nText.\n\n```dax\nTotal = SUM(Sales[Amount])\n```",
        'status' => Article::STATUS_PUBLISHED,
        'published_at' => now()->subDay(),
        ...$overrides,
    ]);
}

function articlesApi(string $query = '')
{
    return test()->withHeaders(test()->apiHeaders())->getJson('/api/v1/articles'.$query)->assertOk();
}

it('lists only published articles, newest first, with filters', function () {
    $bi = Category::query()->create(['name' => 'Business intelligence', 'slug' => 'business-intelligence']);
    $dax = Tag::query()->create(['name' => 'DAX', 'slug' => 'dax']);

    article()->categories()->attach($bi);
    article(['slug' => 'python-etl', 'title' => 'Python ETL', 'body' => 'Pandas pipelines.', 'published_at' => now()->subHour()])->tags()->attach($dax);
    article(['slug' => 'draft-one', 'status' => Article::STATUS_DRAFT]);
    article(['slug' => 'scheduled-one', 'published_at' => now()->addDay()]);

    articlesApi()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.slug', 'python-etl')
        ->assertJsonPath('data.1.categories.0', ['slug' => 'business-intelligence', 'name' => 'Business intelligence'])
        ->assertJsonPath('filters.categories.0.count', 1)
        ->assertJsonPath('filters.tags.0.slug', 'dax');

    expect(collect(articlesApi('?category=business-intelligence')->json('data'))->pluck('slug')->all())->toBe(['power-bi-measures'])
        ->and(collect(articlesApi('?tag=dax')->json('data'))->pluck('slug')->all())->toBe(['python-etl'])
        ->and(collect(articlesApi('?q=SUM(Sales')->json('data'))->pluck('slug')->all())->toBe(['power-bi-measures'])
        ->and(articlesApi('?q=100%25')->json('meta.total'))->toBe(0)
        ->and(articlesApi('?category=<script>')->json('filters.active.category'))->toBeNull();
});

it('serves the full article with contents, code segments and related reading', function () {
    $bi = Category::query()->create(['name' => 'BI', 'slug' => 'bi']);
    article()->categories()->attach($bi);
    article(['slug' => 'related-one', 'published_at' => now()->subWeek()])->categories()->attach($bi);

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/articles/power-bi-measures')
        ->assertOk()
        ->assertJsonPath('data.toc.0', ['id' => 'why-measures', 'text' => 'Why measures', 'level' => 2])
        ->assertJsonPath('data.segments.1.type', 'code')
        ->assertJsonPath('data.segments.1.language', 'dax')
        ->assertJsonPath('data.seo.description', 'DAX patterns that stay fast.')
        ->assertJsonPath('data.related.0.slug', 'related-one');

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/articles/missing')->assertNotFound();
});

it('keeps drafts and scheduled articles private except through a preview link', function () {
    $draft = article(['slug' => 'secret-draft', 'status' => Article::STATUS_DRAFT]);
    $page = app(SavePageDraft::class)->handle(new Page, ['title' => 'P', 'slug' => 'p', 'blocks' => []], null);

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/articles/secret-draft')->assertNotFound();
    resolvePath('/articles/secret-draft')->assertJsonPath('data.status', 'missing');

    $token = app(PreviewToken::class)->issueForArticle($draft);
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/preview/article?token='.$token)
        ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertJsonPath('data.draft', true)->assertJsonPath('data.seo.noindex', true);

    // A page preview link cannot open an article, and vice versa.
    $pageToken = app(PreviewToken::class)->issue($page);
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/preview/article?token='.$pageToken)->assertNotFound();
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/preview/page?token='.$token)->assertNotFound();

    $this->travel(16)->minutes();
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/preview/article?token='.$token)->assertNotFound();
});

it('resolves article addresses and redirects a live article that moved', function () {
    $article = article();
    resolvePath('/articles')->assertJsonPath('data.status', 'page');
    resolvePath('/articles/power-bi-measures')->assertJsonPath('data.status', 'page');

    $article->update(['slug' => 'dax-measures']);

    resolvePath('/articles/power-bi-measures')->assertExactJson(['data' => ['status' => 'redirect', 'to' => '/articles/dax-measures', 'code' => 301]]);
    resolvePath('/articles/dax-measures')->assertJsonPath('data.status', 'page');

    // Drafts have no visitors yet, so renaming them leaves no redirect behind.
    $draft = article(['slug' => 'draft-a', 'status' => Article::STATUS_DRAFT]);
    $draft->update(['slug' => 'draft-b']);
    resolvePath('/articles/draft-a')->assertJsonPath('data.status', 'missing');
});

it('lists articles in the sitemap and in "latest articles" sections', function () {
    article();
    article(['slug' => 'hidden', 'seo' => ['noindex' => true], 'published_at' => now()->subWeek()]);

    expect(sitemapPaths())->toContain('/articles', '/articles/power-bi-measures')->not->toContain('/articles/hidden');

    $page = app(SavePageDraft::class)->handle(new Page, ['title' => 'Blog', 'slug' => 'blog', 'blocks' => [
        ['type' => 'articles', 'data' => ['heading' => 'Latest articles', 'limit' => '3', 'show_all_link' => true, 'visible' => true]],
    ]], null);
    app(PublishPage::class)->handle($page, null);

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/pages/blog')
        ->assertJsonPath('data.blocks.0.props.items.0.slug', 'power-bi-measures')
        ->assertJsonPath('data.blocks.0.props.allUrl', '/articles');
});

it('announces scheduled articles to the website when their time comes', function () {
    Cache::forever(AnnounceScheduledContent::LAST_RUN_KEY, now()->toIso8601String());
    article(['published_at' => now()->addMinutes(5)]);
    resolvePath('/articles/power-bi-measures')->assertJsonPath('data.status', 'missing'); // cached

    Queue::fake();
    $this->travel(6)->minutes();
    $this->artisan('content:announce-scheduled')->assertSuccessful();

    Queue::assertPushed(RefreshWebsitePaths::class, fn (RefreshWebsitePaths $job) => in_array('/articles/power-bi-measures', $job->paths, true));
    resolvePath('/articles/power-bi-measures')->assertJsonPath('data.status', 'page');
});

it('protects a cover image that an article uses', function () {
    Storage::fake('media', ['url' => config('app.url').'/media']);
    $path = tempnam(sys_get_temp_dir(), 'img');
    imagejpeg(imagecreatetruecolor(400, 300), $path);
    $cover = app(StoreMediaUpload::class)->handle(new UploadedFile($path, 'cover.jpg', null, null, true), null, ['alt_text' => 'Cover']);

    article(['cover_media_id' => $cover->id]);

    expect(fn () => app(DeleteMediaAsset::class)->handle($cover, null))->toThrow(ValidationException::class);
});

it('lets editors write, categorise and publish articles, keeping every version', function () {
    $this->actingAs($editor = contentStaff());

    Livewire::test(CreateArticle::class)
        ->fillForm([
            'title' => 'Data modelling basics',
            'slug' => 'data-modelling-basics',
            'excerpt' => 'Stars and snowflakes.',
            'body' => str_repeat('word ', 500)."\n\n## Facts and dimensions",
            'status' => Article::STATUS_PUBLISHED,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()->where('slug', 'data-modelling-basics')->firstOrFail();
    expect($article->isLive())->toBeTrue()
        ->and($article->reading_minutes)->toBe(3)
        ->and($article->author_id)->toBe($editor->id)
        ->and($article->revisions()->count())->toBe(1);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->fillForm(['title' => 'Data modelling, explained'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->fresh()->title)->toBe('Data modelling, explained')
        ->and($article->revisions()->count())->toBe(2);

    $first = $article->revisions()->where('number', 1)->firstOrFail();
    Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $article->fresh(), 'pageClass' => EditArticle::class])
        ->callTableAction('restore', $first);

    expect($article->fresh()->title)->toBe('Data modelling basics')
        ->and($article->fresh()->isLive())->toBeTrue()
        ->and($article->revisions()->first()->note)->toBe('Restored version 1');
});

it('rejects unsafe addresses and duplicate addresses in the admin', function () {
    article();
    $this->actingAs(contentStaff());

    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'X', 'slug' => 'power-bi-measures', 'body' => 'x'])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'X', 'slug' => '../admin', 'body' => 'x'])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('stops writers without publishing rights from publishing or changing live articles', function () {
    $writer = User::factory()->withTwoFactor()->create()->fresh();
    $writer->givePermissionTo([Permission::AdminAccess->value, Permission::CmsManage->value]);
    $this->actingAs($writer->fresh());

    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'Draft by writer', 'slug' => 'draft-by-writer', 'body' => 'Text'])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(Article::query()->where('slug', 'draft-by-writer')->value('status'))->toBe(Article::STATUS_DRAFT);

    $live = article();
    $this->get(EditArticle::getUrl(['record' => $live]))->assertForbidden();
});

it('lists categories and tags with their article counts in the admin', function () {
    $category = Category::query()->create(['name' => 'Business intelligence', 'slug' => 'business-intelligence']);
    $tag = Tag::query()->create(['name' => 'DAX', 'slug' => 'dax']);
    $article = article();
    $article->categories()->attach($category);
    $article->tags()->attach($tag);

    $this->actingAs(contentStaff());
    $this->get(CategoryResource::getUrl())->assertOk()->assertSee('Business intelligence');
    $this->get(TagResource::getUrl())->assertOk()->assertSee('DAX');
    $this->get(EditArticle::getUrl(['record' => $article]))->assertOk();
});

it('hides articles from staff without content permissions', function () {
    $this->actingAs(contentStaff(Role::Moderator))->get('/admin/articles')->assertForbidden();
    $this->actingAs(contentStaff())->get('/admin/articles')->assertOk();
    $this->actingAs(contentStaff())->get(CategoryResource::getUrl())->assertOk();
    $this->actingAs(contentStaff())->get(TagResource::getUrl())->assertOk();
});
