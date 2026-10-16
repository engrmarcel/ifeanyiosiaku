<?php

namespace App\Filament\Resources\Taxonomy;

use App\Domain\Content\Models\Category;
use App\Filament\Resources\Taxonomy\Pages\ManageCategories;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** Article categories. Deleting one keeps its articles (they just lose that category). */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Article categories';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')
                ->required()
                ->maxLength(80)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state, ?Category $record) => $record === null ? $set('slug', Str::slug((string) $state)) : null),
            TextInput::make('slug')
                ->label('Web address')
                ->prefix('/articles?category=')
                ->required()
                ->maxLength(100)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('type', Category::TYPE_ARTICLE)),
            Textarea::make('description')->rows(2)->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug')->label('Address'),
                TextColumn::make('articles_count')->counts('articles')->label('Articles'),
            ])
            ->emptyStateHeading('No categories yet')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', Category::TYPE_ARTICLE);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
