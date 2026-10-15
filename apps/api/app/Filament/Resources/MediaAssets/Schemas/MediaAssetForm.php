<?php

namespace App\Filament\Resources\MediaAssets\Schemas;

use App\Domain\Media\Actions\StoreMediaUpload;
use App\Domain\Media\Models\MediaAsset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MediaAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Image')
                ->visibleOn('create')
                ->schema([
                    FileUpload::make('file')
                        ->label('Image file')
                        ->image()
                        ->acceptedFileTypes(array_keys(StoreMediaUpload::ALLOWED))
                        ->maxSize(StoreMediaUpload::MAX_BYTES / 1024)
                        ->storeFiles(false)
                        ->required()
                        ->helperText('JPEG, PNG, WebP or GIF, up to 15 MB and 8000 × 8000 pixels. Hidden photo data (such as GPS location) is removed automatically.'),
                ]),
            Section::make('Preview')
                ->visibleOn('edit')
                ->schema([
                    TextEntry::make('preview')
                        ->hiddenLabel()
                        ->html()
                        ->state(fn (?MediaAsset $record) => $record ? new HtmlString(sprintf(
                            '<img src="%s" alt="" style="max-height:320px;border-radius:8px">'
                            .'<p style="margin-top:8px;font-size:12px;opacity:.7">%d × %d px · %s · used in %d place(s)</p>',
                            e($record->url('md')),
                            (int) $record->width,
                            (int) $record->height,
                            e(number_format($record->size / 1024, 0).' KB'),
                            $record->usages()->count(),
                        )) : ''),
                ]),
            Section::make('Description')
                ->schema([
                    TextInput::make('title')->maxLength(150),
                    TextInput::make('alt_text')
                        ->label('Alternative text')
                        ->required()
                        ->maxLength(300)
                        ->helperText('Describe the image for people who cannot see it, e.g. "Ifeanyi Osiaku presenting a Power BI dashboard". Required for accessibility and search engines.'),
                    Textarea::make('description')->rows(3)->maxLength(2000),
                ]),
        ]);
    }
}
