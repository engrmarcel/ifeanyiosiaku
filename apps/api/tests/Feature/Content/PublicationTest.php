<?php

use App\Domain\Content\Models\Publication;
use App\Domain\Media\Actions\DeleteMediaAsset;
use App\Domain\Media\Actions\StoreDocumentUpload;
use App\Domain\Media\Models\MediaAsset;
use App\Filament\Resources\Publications\Pages\CreatePublication;
use App\Filament\Resources\Publications\Pages\EditPublication;
use App\Filament\Resources\Publications\RelationManagers\VersionsRelationManager;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('media', ['url' => config('app.url').'/media']);
});

function publication(array $overrides = []): Publication
{
    return Publication::query()->create([
        'slug' => 'bi-adoption-study',
        'title' => 'Business intelligence adoption in SMEs',
        'type' => 'research',
        'abstract' => 'A study of how small firms adopt BI tools.',
        'authors' => ['Ifeanyi Osiaku'],
        'status' => 'published',
        'published_at' => now()->subDay(),
        ...$overrides,
    ]);
}

function publicationPdf(): MediaAsset
{
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

    return app(StoreDocumentUpload::class)->handle(new UploadedFile($path, 'study.pdf', null, null, true), null, ['title' => 'Full study (PDF)']);
}

it('lists published publications and shows versions newest first', function () {
    $pub = publication();
    $pdf = publicationPdf();
    $pub->versions()->create(['version_label' => '1.0', 'released_at' => '2025-01-10', 'external_url' => 'https://doi.org/10.1234/abc']);
    $pub->versions()->create(['version_label' => '1.1', 'released_at' => '2025-06-01', 'changelog' => 'Updated survey data.', 'file_media_id' => $pdf->id]);
    publication(['slug' => 'draft-paper', 'status' => 'draft']);

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/publications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.typeLabel', 'Research')
        ->assertJsonPath('data.0.latestVersion', '1.1')
        ->assertJsonPath('filters.types.0', ['type' => 'research', 'label' => 'Research']);

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/publications/bi-adoption-study')
        ->assertOk()
        ->assertJsonPath('data.versions.0.label', '1.1')
        ->assertJsonPath('data.versions.0.file.name', 'Full study (PDF)')
        ->assertJsonPath('data.versions.0.file.url', $pdf->url())
        ->assertJsonPath('data.versions.1.externalUrl', 'https://doi.org/10.1234/abc')
        ->assertJsonPath('data.authors', ['Ifeanyi Osiaku']);

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/publications/draft-paper')->assertNotFound();
    resolvePath('/publications/bi-adoption-study')->assertJsonPath('data.status', 'page');
    resolvePath('/publications/draft-paper')->assertJsonPath('data.status', 'missing');
    expect(sitemapPaths())->toContain('/publications', '/publications/bi-adoption-study');

    // A PDF that a version offers for download cannot be deleted from the library.
    expect(fn () => app(DeleteMediaAsset::class)->handle($pdf, null))->toThrow(ValidationException::class);
});

it('lets editors create publications and add versions with a file or link', function () {
    $this->actingAs(contentStaff());
    $pdf = publicationPdf();

    Livewire::test(CreatePublication::class)
        ->fillForm([
            'title' => 'Practical DAX',
            'slug' => 'practical-dax',
            'type' => 'ebook',
            'authors' => ['Ifeanyi Osiaku', '  '],
            'isbn' => '978-3-16-148410-0',
            'status' => 'published',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pub = Publication::query()->where('slug', 'practical-dax')->firstOrFail();
    expect($pub->authors)->toBe(['Ifeanyi Osiaku'])->and($pub->published_at)->not->toBeNull();

    $manager = fn () => Livewire::test(VersionsRelationManager::class, ['ownerRecord' => $pub, 'pageClass' => EditPublication::class]);

    $manager()->callAction(TestAction::make('create')->table(), ['version_label' => '1.0', 'released_at' => '2026-10-01'])
        ->assertHasFormErrors(['external_url']);

    $manager()->callAction(TestAction::make('create')->table(), ['version_label' => '1.0', 'released_at' => '2026-10-01', 'external_url' => 'http://insecure.example'])
        ->assertHasFormErrors(['external_url']);

    $manager()->callAction(TestAction::make('create')->table(), ['version_label' => '1.0', 'released_at' => '2026-10-01', 'file_media_id' => $pdf->id])
        ->assertHasNoFormErrors();

    expect($pub->versions()->count())->toBe(1);
});

it('rejects invalid identifiers', function () {
    $this->actingAs(contentStaff());

    Livewire::test(CreatePublication::class)
        ->fillForm(['title' => 'X', 'slug' => 'x', 'type' => 'paper', 'doi' => 'not-a-doi', 'isbn' => '12'])
        ->call('create')
        ->assertHasFormErrors(['doi', 'isbn']);
});
