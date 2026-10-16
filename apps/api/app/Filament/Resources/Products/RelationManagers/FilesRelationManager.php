<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Models\Product;
use App\Domain\Commerce\Models\ProductFile;
use App\Domain\Commerce\Store;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Files buyers receive. Stored on the private "products" disk under a random name; they have no
 * public address and are only ever sent as downloads after a purchase check.
 */
class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Files buyers receive';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        return $schema->columns(2)->components([
            TextInput::make('label')->label('Name shown to buyers')->required()->maxLength(150)->placeholder('Workbook (PDF)'),
            Select::make('product_version_id')
                ->label('Version (optional)')
                ->relationship('version', 'version_label', fn (Builder $query) => $query->where('product_id', $product->getKey())),
            FileUpload::make('path')
                ->label('File')
                ->disk('products')
                ->directory($product->ulid)
                ->visibility('private')
                ->storeFileNamesIn('original_name')
                ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => strtolower((string) str()->ulid()).'.'.strtolower($file->getClientOriginalExtension()))
                ->rule('extensions:'.implode(',', ProductFile::ALLOWED_EXTENSIONS))
                ->maxSize(ProductFile::MAX_KILOBYTES)
                ->required()
                ->downloadable(false)
                ->openable(false)
                ->previewable(false)
                ->columnSpanFull()
                ->helperText('Up to 512 MB. Allowed: '.implode(', ', ProductFile::ALLOWED_EXTENSIONS).'. Stored privately; buyers download it after purchase.'),
            TextInput::make('download_limit')
                ->label('Downloads allowed per buyer')
                ->numeric()->minValue(1)->maxValue(1000)
                ->placeholder((string) app(Store::class)->downloadLimit())
                ->helperText('Leave empty to use the store default.'),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('original_name')->label('File')->limit(40),
                TextColumn::make('size')->formatStateUsing(fn (int $state) => $state >= 1048576 ? number_format($state / 1048576, 1).' MB' : number_format(max(1, $state / 1024)).' KB'),
                TextColumn::make('version.version_label')->label('Version')->placeholder('—'),
                TextColumn::make('download_limit')->label('Download limit')->placeholder('Default'),
            ])
            ->headerActions([
                CreateAction::make()->label('Upload file')->after(fn (ProductFile $record) => $this->audit('product.file_added', $record)),
            ])
            ->emptyStateHeading('No files yet')
            ->emptyStateDescription('Upload the files buyers receive. Bundles need no files of their own.')
            ->recordActions([
                EditAction::make()->after(fn (ProductFile $record) => $this->audit('product.file_updated', $record)),
                DeleteAction::make()
                    ->modalDescription('The file is deleted from private storage. Buyers will no longer be able to download it.')
                    ->after(fn (ProductFile $record) => $this->audit('product.file_deleted', $record)),
            ]);
    }

    private function audit(string $action, ProductFile $file): void
    {
        $actor = Auth::user();
        app(AuditLogger::class)->record($action, $file, [
            'product' => $file->product_id,
            'label' => $file->label,
            'name' => $file->original_name,
        ], $actor instanceof User ? $actor : null);
    }
}
