<?php

namespace App\Filament\Resources\Portfolio;

use App\Domain\Cms\Blocks\Fields;
use App\Domain\Portfolio\Models\Certification;
use App\Filament\Resources\Portfolio\Pages\ManageCertifications;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Certifications and qualifications. Starts empty: add only genuine, verifiable credentials. */
class CertificationResource extends Resource
{
    protected static ?string $model = Certification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Portfolio';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(160),
            TextInput::make('issuer')->maxLength(160),
            DatePicker::make('issued_on')->native(false),
            DatePicker::make('expires_on')->native(false)->after('issued_on'),
            TextInput::make('credential_id')->label('Credential ID')->maxLength(120),
            Fields::link('credential_url', 'Verification link'),
            Fields::mediaById('media_id', 'Badge or certificate image'),
            Toggle::make('is_visible')->label('Show on the website')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('issuer')->placeholder('—'),
                TextColumn::make('issued_on')->date('M Y')->placeholder('—'),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
            ])
            ->emptyStateHeading('No certifications added yet')
            ->emptyStateDescription('The Certifications section stays hidden on the website until entries are added.')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCertifications::route('/')];
    }
}
