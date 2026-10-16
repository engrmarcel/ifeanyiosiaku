<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Released versions, shown on the product page as a changelog. Files can belong to a version. */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Versions';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('version_label')->label('Version')->required()->maxLength(40)->placeholder('1.0')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $this->getOwnerRecord()->getKey())),
            DatePicker::make('released_at')->label('Release date')->required()->default(now()),
            Textarea::make('changelog')->label('What changed')->rows(3)->maxLength(3000)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('released_at', 'desc')
            ->columns([
                TextColumn::make('version_label')->label('Version'),
                TextColumn::make('released_at')->label('Released')->date(),
                TextColumn::make('changelog')->limit(60)->placeholder('—'),
                TextColumn::make('files_count')->counts('files')->label('Files'),
            ])
            ->headerActions([CreateAction::make()->label('Add version')])
            ->emptyStateHeading('No versions')
            ->emptyStateDescription('Optional: add versions to show a changelog and organise files.')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
