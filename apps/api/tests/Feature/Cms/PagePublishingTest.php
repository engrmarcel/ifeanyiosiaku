<?php

use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\RestorePageRevision;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Actions\SetHomepage;
use App\Domain\Cms\Actions\UnpublishPage;
use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\Redirect;
use App\Domain\Cms\PreviewToken;
use App\Domain\Identity\Enums\Role;
use App\Domain\Media\Actions\StoreMediaUpload;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function editor(): User
{
    return User::factory()->withTwoFactor()->role(Role::Editor)->create()->fresh();
}

/** @param list<array<string, mixed>> $blocks */
function draft(array $overrides = [], ?Page $page = null): Page
{
    return app(SavePageDraft::class)->handle($page ?? new Page, [
        'title' => 'About',
        'slug' => 'about',
        'blocks' => [
            ['type' => 'heading', 'data' => ['text' => 'About Ifeanyi', 'level' => '2']],
            ['type' => 'rich_text', 'data' => ['body' => '<p>Software, AI and data.</p>']],
        ],
        ...$overrides,
    ], null);
}

function publish(Page $page): Page
{
    return app(PublishPage::class)->handle($page, null);
}

function api(string $path)
{
    return test()->withHeaders(test()->apiHeaders())->getJson($path);
}

it('saves drafts that visitors cannot see', function () {
    $page = draft();

    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->revisions()->where('kind', 'draft')->count())->toBe(1);

    api('/api/v1/pages/about')->assertNotFound()->assertJsonPath('error.code', 'not_found');
});

it('publishes the draft and serves it to visitors', function () {
    $page = publish(draft());

    expect($page->status)->toBe(PageStatus::Published)->and($page->published_revision_id)->not->toBeNull();

    api('/api/v1/pages/about')
        ->assertOk()
        ->assertJsonPath('data.title', 'About')
        ->assertJsonPath('data.path', '/about')
        ->assertJsonPath('data.blocks.0.type', 'heading')
        ->assertJsonPath('data.blocks.0.props.text', 'About Ifeanyi')
        ->assertJsonPath('data.blocks.1.props.html', '<p>Software, AI and data.</p>');

    $this->assertDatabaseHas('audit_logs', ['action' => 'page.published', 'subject_id' => $page->id]);
});

it('keeps showing the published version while a newer draft is edited', function () {
    $page = publish(draft());
    draft(['title' => 'About (draft edit)', 'blocks' => [['type' => 'heading', 'data' => ['text' => 'Unreleased']]]], $page);

    api('/api/v1/pages/about')->assertOk()->assertJsonPath('data.title', 'About')->assertJsonPath('data.blocks.0.props.text', 'About Ifeanyi');

    publish($page->fresh());
    api('/api/v1/pages/about')->assertOk()->assertJsonPath('data.blocks.0.props.text', 'Unreleased');
});

it('shows drafts through a signed preview link that expires', function () {
    $page = draft(['title' => 'Secret draft']);
    $token = app(PreviewToken::class)->issue($page);

    api('/api/v1/preview/page?token='.$token)
        ->assertOk()
        ->assertJsonPath('data.title', 'Secret draft')
        ->assertJsonPath('data.draft', true)
        ->assertJsonPath('data.seo.noindex', true)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    api('/api/v1/preview/page?token='.$token.'x')->assertNotFound()->assertJsonPath('error.code', 'preview_expired');

    $this->travel(16)->minutes();
    api('/api/v1/preview/page?token='.$token)->assertNotFound();
});

it('rejects preview tokens forged for another page', function () {
    $mine = draft();
    $other = draft(['slug' => 'private', 'title' => 'Private']);
    [$payload, $signature] = explode('.', app(PreviewToken::class)->issue($mine));
    $forged = rtrim(strtr(base64_encode(json_encode(['p' => $other->ulid, 'e' => now()->addHour()->timestamp])), '+/', '-_'), '=');

    api('/api/v1/preview/page?token='.$forged.'.'.$signature)->assertNotFound();
});

it('redirects the old address when a published page is renamed', function () {
    $page = publish(draft());
    publish(draft(['slug' => 'about-me'], $page));

    api('/api/v1/pages/about-me')->assertOk();
    api('/api/v1/pages/about')
        ->assertNotFound()
        ->assertJsonPath('error.redirect.to', '/about-me')
        ->assertJsonPath('error.redirect.status', 301);

    expect(Redirect::query()->where('from_path', '/about')->value('hits'))->toBe(1);

    // Renaming again keeps every old address pointing at the newest one, without loops.
    publish(draft(['slug' => 'profile'], $page->fresh()));
    expect(Redirect::query()->where('from_path', '/about')->value('to_path'))->toBe('/profile')
        ->and(Redirect::query()->where('from_path', '/about-me')->value('to_path'))->toBe('/profile');

    publish(draft(['slug' => 'about'], $page->fresh()));
    expect(Redirect::query()->where('from_path', '/about')->exists())->toBeFalse();
    api('/api/v1/pages/about')->assertOk();
});

it('takes unpublished pages off the website', function () {
    $page = publish(draft());
    app(UnpublishPage::class)->handle($page, null);

    api('/api/v1/pages/about')->assertNotFound();
    expect($page->fresh()->status)->toBe(PageStatus::Draft);
});

it('serves the homepage at "/" and redirects its own slug there', function () {
    $page = publish(draft(['slug' => 'home-page', 'title' => 'Home']));

    api('/api/v1/pages/home')->assertNotFound();

    app(SetHomepage::class)->handle($page, null);

    api('/api/v1/pages/home')->assertOk()->assertJsonPath('data.path', '/')->assertJsonPath('data.isHomepage', true);
    api('/api/v1/pages/home-page')->assertNotFound()->assertJsonPath('error.redirect.to', '/');
});

it('protects the homepage from being unpublished or switched to an unpublished page', function () {
    $home = publish(draft(['slug' => 'start']));
    app(SetHomepage::class)->handle($home, null);

    expect(fn () => app(UnpublishPage::class)->handle($home->fresh(), null))->toThrow(ValidationException::class);
    expect(fn () => app(SetHomepage::class)->handle(draft(['slug' => 'unpublished']), null))->toThrow(ValidationException::class);
});

it('publishes and unpublishes on schedule', function () {
    $page = draft(['publish_at' => now()->addHour()->toDateTimeString(), 'unpublish_at' => now()->addHours(3)->toDateTimeString()]);
    expect($page->status)->toBe(PageStatus::Scheduled);

    $this->artisan('cms:publish-scheduled')->assertSuccessful();
    api('/api/v1/pages/about')->assertNotFound();

    $this->travel(61)->minutes();
    $this->artisan('cms:publish-scheduled')->assertSuccessful();
    api('/api/v1/pages/about')->assertOk();

    $this->travel(3)->hours();
    $this->artisan('cms:publish-scheduled')->assertSuccessful();
    api('/api/v1/pages/about')->assertNotFound();
});

it('restores an earlier version into the draft without changing the live page', function () {
    $page = publish(draft());
    $first = $page->revisions()->where('kind', 'publish')->firstOrFail();
    publish(draft(['title' => 'About v2'], $page));

    app(RestorePageRevision::class)->handle($page->fresh(), $first, null);

    expect($page->fresh()->title)->toBe('About');
    api('/api/v1/pages/about')->assertJsonPath('data.title', 'About v2');
    expect($page->revisions()->where('kind', 'restore')->exists())->toBeTrue();
});

it('validates page addresses', function (string $slug, string $message) {
    expect(fn () => draft(['slug' => $slug]))->toThrow(ValidationException::class, $message);
})->with([
    'reserved' => ['admin', 'invalid'],
    'reserved prefix' => ['api/things', 'reserved'],
    'uppercase' => ['About', 'lowercase'],
    'spaces' => ['about me', 'lowercase'],
]);

it('refuses duplicate addresses', function () {
    draft();
    draft(['title' => 'Copy']);
})->throws(ValidationException::class, 'already been taken');

it('reports which block is invalid', function () {
    draft(['blocks' => [
        ['type' => 'heading', 'data' => ['text' => 'Fine']],
        ['type' => 'cta', 'data' => ['heading' => 'Book', 'primary_label' => 'Go', 'primary_url' => 'javascript:alert(1)']],
    ]]);
})->throws(ValidationException::class, 'Block 2 (Call to action)');

it('rejects unknown block types', function () {
    draft(['blocks' => [['type' => 'evil', 'data' => []]]]);
})->throws(ValidationException::class, 'unknown block type');

it('strips scripts and unsafe links from rich text', function () {
    $page = publish(draft(['blocks' => [['type' => 'rich_text', 'data' => [
        'body' => '<p onclick="steal()">Hi <a href="javascript:alert(1)">x</a> <a href="https://ok.example">ok</a></p><script>alert(1)</script><img src=x onerror=alert(1)>',
    ]]]]));

    $stored = $page->fresh()->blocks[0]['data']['body'];
    $html = api('/api/v1/pages/about')->json('data.blocks.0.props.html');

    foreach ([$stored, $html] as $output) {
        expect($output)->not->toContain('<script')->not->toContain('onclick')->not->toContain('javascript:')->not->toContain('onerror')
            ->toContain('href="https://ok.example"')->toContain('rel="noopener noreferrer nofollow"');
    }
});

it('omits hidden blocks from the website', function () {
    publish(draft(['blocks' => [
        ['type' => 'heading', 'data' => ['text' => 'Visible']],
        ['type' => 'heading', 'data' => ['text' => 'Hidden', 'visible' => false]],
    ]]));

    $blocks = api('/api/v1/pages/about')->json('data.blocks');
    expect($blocks)->toHaveCount(1)->and($blocks[0]['props']['text'])->toBe('Visible');
});

it('resolves images with alt text and records where media is used', function () {
    Storage::fake('media', ['url' => config('app.url').'/media']);
    $path = tempnam(sys_get_temp_dir(), 'img');
    imagejpeg(imagecreatetruecolor(1400, 900), $path);
    $asset = app(StoreMediaUpload::class)->handle(new UploadedFile($path, 'team.jpg', null, null, true), null, ['alt_text' => 'The team']);

    $page = publish(draft(['blocks' => [['type' => 'image', 'data' => ['media' => $asset->ulid, 'caption' => 'Workshop']]]]));

    api('/api/v1/pages/about')
        ->assertJsonPath('data.blocks.0.props.media.alt', 'The team')
        ->assertJsonPath('data.blocks.0.props.media.width', 1400)
        ->assertJsonCount(3, 'data.blocks.0.props.media.variants');

    expect(MediaUsage::query()->where('usable_id', $page->id)->pluck('field')->sort()->values()->all())->toBe(['draft', 'published']);
});

it('parses video, code highlight and table blocks safely', function () {
    publish(draft(['blocks' => [
        ['type' => 'video', 'data' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Intro']],
        ['type' => 'code', 'data' => ['language' => 'php', 'code' => "<?php\necho 1;", 'highlight' => '2, 4-5']],
        ['type' => 'table', 'data' => ['caption' => 'Tools', 'rows' => "Tool | Use\nPower BI | Dashboards", 'header' => true]],
    ]]));

    $blocks = api('/api/v1/pages/about')->json('data.blocks');

    expect($blocks[0]['props'])->toMatchArray(['provider' => 'youtube', 'videoId' => 'dQw4w9WgXcQ'])
        ->and($blocks[1]['props']['code'])->toBe("<?php\necho 1;")
        ->and($blocks[1]['props']['highlight'])->toBe([2, 4, 5])
        ->and($blocks[2]['props']['header'])->toBe(['Tool', 'Use'])
        ->and($blocks[2]['props']['rows'])->toBe([['Power BI', 'Dashboards']]);
});

it('only accepts YouTube and Vimeo videos', function () {
    draft(['blocks' => [['type' => 'video', 'data' => ['url' => 'https://evil.example/video', 'title' => 'x']]]]);
})->throws(ValidationException::class, 'Only YouTube and Vimeo');

it('limits page management to content staff', function () {
    $this->actingAs(editor())->get('/admin/pages')->assertOk();
    $this->actingAs(User::factory()->withTwoFactor()->role(Role::Instructor)->create()->fresh())->get('/admin/pages')->assertForbidden();
});
