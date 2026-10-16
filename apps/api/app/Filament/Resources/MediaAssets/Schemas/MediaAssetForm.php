<?php

namespace App\Filament\Resources\MediaAssets\Schemas;

use App\Domain\Media\Actions\StoreDocumentUpload;
use App\Domain\Media\Actions\StoreMediaUpload;
use App\Domain\Media\Models\MediaAsset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;

class MediaAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('File')
                ->visibleOn('create')
                ->schema([
                    FileUpload::make('file')
                        ->label('Image or PDF')
                        ->acceptedFileTypes([...array_keys(StoreMediaUpload::ALLOWED), ...array_keys(StoreDocumentUpload::ALLOWED)])
                        ->maxSize(StoreDocumentUpload::MAX_BYTES / 1024)
                        ->storeFiles(false)
                        ->required()
                        ->helperText('Images: JPEG, PNG, WebP or GIF, up to 15 MB and 8000 × 8000 pixels; hidden photo data (such as GPS location) is removed automatically. PDFs: up to 30 MB; PDFs with scripts or embedded files are refused. PDFs are stored unchanged, so check the document properties (author, company) before uploading.'),
                ]),
            Section::make('Preview')
                ->visibleOn('edit')
                ->schema([
                    TextEntry::make('preview')
                        ->hiddenLabel()
                        ->html()
                        ->state(fn (?MediaAsset $record) => match (true) {
                            $record === null => '',
                            $record->isImage() => new HtmlString(sprintf(
                                '<img src="%s" alt="" style="max-height:320px;border-radius:8px">'
                                .'<p style="margin-top:8px;font-size:12px;opacity:.7">%d × %d px · %s · used in %d place(s)</p>',
                                e($record->url('md')),
                                (int) $record->width,
                                (int) $record->height,
                                e(number_format($record->size / 1024, 0).' KB'),
                                $record->usages()->count(),
                            )),
                            default => new HtmlString(sprintf(
                                '<p>PDF document · %s · used in %d place(s) · <a href="%s" style="text-decoration:underline">Download</a></p>',
                                e(number_format($record->size / 1024, 0).' KB'),
                                $record->usages()->count(),
                                e($record->url()),
                            )),
                        }),
                ]),
            Section::make('Description')
                ->schema([
                    TextInput::make('title')
                        ->maxLength(150)
                        ->required(fn (?MediaAsset $record): bool => $record !== null && ! $record->isImage())
                        ->helperText('Required for PDFs: visitors see it on the download button.'),
                    TextInput::make('alt_text')
                        ->label('Alternative text')
                        ->required(fn (?MediaAsset $record, Get $get): bool => $record !== null ? $record->isImage() : ! self::isDocument($get('file')))
                        ->hidden(fn (?MediaAsset $record): bool => $record !== null && ! $record->isImage())
                        ->maxLength(300)
                        ->helperText('Images only. Describe the image for people who cannot see it, e.g. "Ifeanyi Osiaku presenting a Power BI dashboard". Required for accessibility and search engines.'),
                    Textarea::make('description')->rows(3)->maxLength(2000),
                ]),
        ]);
    }

    /** Whether the file chosen for upload is a document rather than an image. */
    private static function isDocument(mixed $upload): bool
    {
        $file = is_array($upload) ? reset($upload) : $upload;

        return $file instanceof UploadedFile && array_key_exists((string) $file->getMimeType(), StoreDocumentUpload::ALLOWED);
    }
}
