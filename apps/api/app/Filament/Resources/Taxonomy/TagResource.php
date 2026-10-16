<?php

namespace App\Filament\Resources\Taxonomy;

use App\Domain\Content\Models\Tag;
use App\Filament\Resources\Taxonomy\Pages\ManageTags;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Tags shared by articles (and later other content). */
class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')
                ->required()
                ->maxLength(60)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state, ?Tag $record) => $record === null ? $set('slug', Str::slug((string) $state)) : null),
            TextInput::make('slug')
                ->label('Web address')
                ->prefix('/articles?tag=')
                ->required()
                ->maxLength(80)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->label('Address'),
                TextColumn::make('articles_count')->counts('articles')->label('Articles')->sortable(),
            ])
            ->emptyStateHeading('No tags yet')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTags::route('/')];
    }
}
