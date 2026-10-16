<?php

namespace App\Filament\Resources\Taxonomy;

use App\Domain\Content\Models\Category;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Settings\Addons;
use App\Domain\Settings\Enums\Addon;
use App\Filament\Resources\Taxonomy\Pages\ManageStoreCategories;
use App\Models\User;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** Store categories (separate from article categories). Only while the Commerce add-on is on. */
class StoreCategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $slug = 'store-categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?string $navigationLabel = 'Store categories';

    protected static ?string $modelLabel = 'store category';

    protected static ?int $navigationSort = 2;

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
                ->prefix('/store?category=')
                ->required()
                ->maxLength(100)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('type', Category::TYPE_PRODUCT)),
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
                TextColumn::make('products_count')->counts('products')->label('Products'),
            ])
            ->emptyStateHeading('No store categories yet')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', Category::TYPE_PRODUCT);
    }

    private static function allowed(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Addons::class)->enabled(Addon::Commerce) && $user->can(Permission::CommerceManage->value);
    }

    public static function canViewAny(): bool
    {
        return self::allowed();
    }

    public static function canCreate(): bool
    {
        return self::allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return self::allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return self::allowed();
    }

    public static function getPages(): array
    {
        return ['index' => ManageStoreCategories::route('/')];
    }
}
