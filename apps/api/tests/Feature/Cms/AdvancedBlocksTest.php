<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\PreviewToken;
use App\Domain\Identity\Enums\Role;
use App\Domain\Media\Actions\DeleteMediaAsset;
use App\Domain\Media\Actions\StoreDocumentUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function rawBlock(string $html = '<div id="chart">Chart</div><script>document.getElementById("chart").textContent = "Drawn";</script>', array $extra = []): array
{
    return ['type' => 'raw_html', 'data' => ['title' => 'Sales chart', 'html' => $html, 'height_mode' => 'auto', 'visible' => true, ...$extra]];
}

function superAdmin(): User
{
    return contentStaff(Role::SuperAdmin);
}

/** Saves and publishes a page with these blocks; returns its public block payloads. */
function publishBlocks(array $blocks, ?User $actor = null, string $slug = 'lab'): array
{
    $page = Page::query()->where('slug', $slug)->first() ?? new Page;
    $page = app(SavePageDraft::class)->handle($page, ['title' => 'Lab', 'slug' => $slug, 'blocks' => $blocks], $actor ?? superAdmin());
    app(PublishPage::class)->handle($page, null);

    return test()->withHeaders(test()->apiHeaders())->getJson("/api/v1/pages/{$slug}")->assertOk()->json('data.blocks');
}

it('serves custom code in an isolated, framable-only-by-us document', function () {
    $blocks = publishBlocks([rawBlock()]);
    $src = $blocks[0]['props']['src'];

    expect($blocks[0]['props']['title'])->toBe('Sales chart')
        ->and($src)->toStartWith('/api/v1/embeds/')
        ->and($blocks[0]['props'])->not->toHaveKey('html'); // the code itself is never put into the page

    $response = $this->get($src)->assertOk();
    expect($response->headers->get('Content-Security-Policy'))->toStartWith('sandbox allow-scripts')->toContain("frame-ancestors 'self'")
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->getContent())->toContain('<div id="chart">Chart</div>')->toContain('io-embed-height');

    // Other API responses still refuse to be framed.
    expect($this->getJson('/api/v1/health')->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('stops serving the old code once it changes, and never serves forged or unpublished embeds', function () {
    $old = publishBlocks([rawBlock()])[0]['props']['src'];
    $new = publishBlocks([rawBlock('<p>Version two</p>')])[0]['props']['src'];

    $this->get($old)->assertNotFound();
    $this->get($new)->assertOk()->assertSee('Version two', false);

    [$payload, $signature] = explode('.', substr($new, strlen('/api/v1/embeds/')));
    $forged = rtrim(strtr(base64_encode((string) json_encode(['p' => 'x', 'i' => 0, 'h' => 'y', 'd' => false])), '+/', '-_'), '=');
    $this->get("/api/v1/embeds/{$forged}.{$signature}")->assertNotFound();

    Page::query()->where('slug', 'lab')->firstOrFail()->update(['status' => 'draft']);
    $this->get($new)->assertNotFound();
});

it('previews custom code in drafts through a short-lived link', function () {
    $page = app(SavePageDraft::class)->handle(new Page, ['title' => 'Draft', 'slug' => 'draft-lab', 'blocks' => [rawBlock('<p>Draft code</p>')]], superAdmin());
    $token = app(PreviewToken::class)->issue($page);
    $src = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/preview/page?token='.$token)->json('data.blocks.0.props.src');

    $this->get($src)->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    $this->travel(16)->minutes();
    $this->get($src)->assertNotFound();
});

it('lets only Super Administrators add or change custom code, and audits every change', function () {
    $editor = contentStaff(Role::Editor);

    expect(fn () => app(SavePageDraft::class)->handle(new Page, ['title' => 'X', 'slug' => 'x', 'blocks' => [rawBlock()]], $editor))
        ->toThrow(ValidationException::class);

    // A Super Administrator adds it; an editor may then move, hide or remove it, but not change it.
    publishBlocks([rawBlock()]);
    $page = Page::query()->where('slug', 'lab')->firstOrFail();
    $heading = ['type' => 'heading', 'data' => ['text' => 'Intro', 'level' => '2', 'align' => 'left', 'visible' => true]];

    app(SavePageDraft::class)->handle($page, ['title' => 'Lab', 'slug' => 'lab', 'blocks' => [$heading, rawBlock(extra: ['visible' => false])]], $editor);
    app(SavePageDraft::class)->handle($page->fresh(), ['title' => 'Lab', 'slug' => 'lab', 'blocks' => [$heading]], $editor);

    expect(fn () => app(SavePageDraft::class)->handle($page->fresh(), ['title' => 'Lab', 'slug' => 'lab', 'blocks' => [$heading, rawBlock('<script>steal()</script>')]], $editor))
        ->toThrow(ValidationException::class);

    expect(AuditLog::query()->where('action', 'page.raw_html_changed')->count())->toBe(1);
});

it('treats a resubmitted unchanged block as unchanged even if numbers come back as text', function () {
    $editor = contentStaff(Role::Editor);
    publishBlocks([rawBlock(extra: ['height_mode' => 'fixed', 'height' => 400])]);
    $page = Page::query()->where('slug', 'lab')->firstOrFail();

    app(SavePageDraft::class)->handle($page, ['title' => 'Lab (renamed)', 'slug' => 'lab', 'blocks' => [rawBlock(extra: ['height_mode' => 'fixed', 'height' => '400'])]], $editor);

    expect($page->fresh()->title)->toBe('Lab (renamed)');
});

it('lists PDF downloads with their size and protects them from deletion', function () {
    Storage::fake('media', ['url' => config('app.url').'/media']);
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    $pdf = app(StoreDocumentUpload::class)->handle(new UploadedFile($path, 'guide.pdf', null, null, true), null, ['title' => 'DAX guide']);

    $blocks = publishBlocks([['type' => 'downloads', 'data' => [
        'heading' => 'Resources',
        'files' => [['media' => $pdf->ulid, 'description' => 'Cheat sheet'], ['media' => '01missingmissingmissing00']],
        'visible' => true,
    ]]]);

    expect($blocks[0]['props']['files'])->toBe([[
        'name' => 'DAX guide', 'description' => 'Cheat sheet', 'url' => $pdf->url(), 'size' => $pdf->size,
    ]]);
    expect(fn () => app(DeleteMediaAsset::class)->handle($pdf, null))->toThrow(ValidationException::class);
});

it('accepts Mermaid diagrams with a description and refuses configuration directives', function () {
    $diagram = fn (string $source) => ['type' => 'diagram', 'data' => ['source' => $source, 'description' => 'Data flows from the sales database to the report.', 'visible' => true]];

    $blocks = publishBlocks([$diagram("flowchart LR\n  A[Sales DB] --> B[Report]")]);
    expect($blocks[0]['props'])->toMatchArray(['source' => "flowchart LR\n  A[Sales DB] --> B[Report]", 'showSource' => false]);

    expect(fn () => publishBlocks([$diagram("%%{init: {'securityLevel': 'loose'}}%%\nflowchart LR\n A-->B")]))->toThrow(ValidationException::class);
});
