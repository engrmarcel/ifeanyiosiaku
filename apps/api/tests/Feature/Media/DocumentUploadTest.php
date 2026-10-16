<?php

use App\Domain\Cms\Blocks\BlockRegistry;
use App\Domain\Media\Actions\StoreDocumentUpload;
use App\Domain\Media\Models\MediaAsset;
use App\Filament\Resources\MediaAssets\Pages\CreateMediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('media', ['url' => config('app.url').'/media']);
});

/** A small, valid PDF; $extra is inserted as an additional object. */
function pdfContent(string $extra = ''): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
        .$extra
        ."trailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

function pdfUpload(string $extra = '', string $name = 'Research Paper.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, pdfContent($extra));

    return new UploadedFile($path, $name, null, null, true);
}

function storePdf(UploadedFile $file): MediaAsset
{
    return app(StoreDocumentUpload::class)->handle($file, null, ['title' => 'Research paper']);
}

it('stores a plain PDF and always serves it as a download', function () {
    $asset = storePdf(pdfUpload());

    expect($asset->mime)->toBe('application/pdf')
        ->and($asset->filename)->toBe('research-paper.pdf')
        ->and($asset->width)->toBeNull();
    Storage::disk('media')->assertExists($asset->path());

    $response = $this->get('/media/'.$asset->path());
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('refuses PDFs with scripts, launch actions or embedded files', function (string $object) {
    expect(fn () => storePdf(pdfUpload($object)))->toThrow(ValidationException::class);
    expect(MediaAsset::query()->count())->toBe(0);
})->with([
    'javascript' => "4 0 obj\n<< /S /JavaScript /JS (app.alert(1)) >>\nendobj\n",
    'hex-escaped name' => "4 0 obj\n<< /S /J#61vaScript >>\nendobj\n",
    'launch action' => "4 0 obj\n<< /S /Launch /F (calc.exe) >>\nendobj\n",
    'embedded file' => "4 0 obj\n<< /Type /EmbeddedFile >>\nendobj\n",
    'compressed script' => "4 0 obj\n<< /Filter /FlateDecode >>\nstream\n".gzcompress('<< /S /JavaScript /JS (x) >>')."\nendstream\nendobj\n",
]);

it('accepts ordinary open actions and names that only look similar', function () {
    $asset = storePdf(pdfUpload("4 0 obj\n<< /OpenAction [3 0 R /Fit] /JSON (x) >>\nendobj\n"));

    expect($asset->exists)->toBeTrue();
});

it('refuses files that are not really PDFs', function () {
    $path = tempnam(sys_get_temp_dir(), 'fake');
    file_put_contents($path, '<html><script>alert(1)</script></html>');

    expect(fn () => storePdf(new UploadedFile($path, 'paper.pdf', 'application/pdf', null, true)))->toThrow(ValidationException::class);
});

it('uploads PDFs from the media library screen and requires a title', function () {
    $this->actingAs(contentStaff());

    Livewire::test(CreateMediaAsset::class)
        ->set('data.file', UploadedFile::fake()->createWithContent('paper.pdf', pdfContent()))
        ->set('data.title', '')
        ->call('create')
        ->assertHasFormErrors(['title']);

    Livewire::test(CreateMediaAsset::class)
        ->set('data.file', UploadedFile::fake()->createWithContent('paper.pdf', pdfContent()))
        ->set('data.title', 'White paper')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MediaAsset::query()->where('mime', 'application/pdf')->value('title'))->toBe('White paper');
});

it('never shows a document inside an image block', function () {
    $pdf = storePdf(pdfUpload());

    $public = app(BlockRegistry::class)->toPublic([
        ['type' => 'image', 'data' => ['media' => $pdf->ulid, 'size' => 'normal', 'visible' => true]],
    ]);

    expect($public[0]['props']['media'] ?? null)->toBeNull();
});
