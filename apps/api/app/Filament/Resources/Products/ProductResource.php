<?php

namespace App\Filament\Resources\Products;

use App\Domain\Cms\Blocks\Fields;
use App\Domain\Commerce\Enums\ProductType;
use App\Domain\Commerce\Models\Product;
use App\Domain\Commerce\Money;
use App\Domain\Commerce\Store;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Tag;
use App\Domain\Media\Models\MediaAsset;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\FilesRelationManager;
use App\Filament\Resources\Products\RelationManagers\VersionsRelationManager;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use UnitEnum;

/** Store products: what is sold, for how much, and the private files buyers receive. */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Product')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?Product $record) {
                            if ($record === null && blank($get('slug'))) {
                                $set('slug', Str::limit(Str::slug((string) $state), 150, ''));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Web address')
                        ->prefix('/store/')
                        ->required()
                        ->maxLength(150)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->rule(fn (?Product $record) => Rule::unique('products', 'slug')->ignore($record?->getKey())),
                    Select::make('type')
                        ->options(fn (?Product $record) => $record !== null && ! array_key_exists($record->type->value, ProductType::storeCreatable())
                            ? [$record->type->value => $record->type->label()]
                            : ProductType::storeCreatable())
                        ->required()
                        ->live()
                        ->default(ProductType::Pdf->value),
                    Textarea::make('summary')->rows(2)->maxLength(500)->columnSpanFull()
                        ->helperText('One or two sentences for the store list and search results.'),
                    Fields::richText('description', 'Description')->columnSpanFull(),
                    Select::make('bundleItems')
                        ->label('Products in this bundle')
                        ->relationship('bundleItems', 'title', fn (Builder $query, ?Product $record) => $query
                            ->where('type', '!=', ProductType::Bundle->value)
                            ->whereKeyNot($record?->getKey() ?? 0))
                        ->multiple()
                        ->preload()
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('type') === ProductType::Bundle->value)
                        ->helperText('Buyers of the bundle receive every product listed here.'),
                    Textarea::make('licence_terms')->label('Licence terms (optional)')->rows(3)->maxLength(5000)->columnSpanFull()
                        ->helperText('What buyers may do with the product, e.g. "Personal and internal business use. No resale."'),
                ]),
            Section::make('Price')
                ->columnSpan(1)
                ->schema([
                    Toggle::make('is_free')
                        ->label('Free product')
                        ->live()
                        ->helperText('Visitors with an account get it at no cost.'),
                    Repeater::make('prices')
                        ->relationship()
                        ->hidden(fn (Get $get): bool => (bool) $get('is_free'))
                        ->schema([
                            Select::make('currency')
                                ->options(fn () => array_intersect_key(Money::SUPPORTED, array_flip(app(Store::class)->currencies())))
                                ->default(fn () => app(Store::class)->defaultCurrency())
                                ->required()
                                ->distinct(),
                            TextInput::make('amount')
                                ->label('Price')
                                ->required()
                                ->formatStateUsing(fn ($state, Get $get) => is_numeric($state) ? Money::toMajor((int) $state, (string) ($get('currency') ?: 'NGN')) : $state)
                                ->dehydrateStateUsing(fn ($state, Get $get) => self::minorOrNull($state, $get('currency')))
                                ->rule(self::moneyRule(required: true)),
                            TextInput::make('compare_at_amount')
                                ->label('Was (optional)')
                                ->formatStateUsing(fn ($state, Get $get) => is_numeric($state) ? Money::toMajor((int) $state, (string) ($get('currency') ?: 'NGN')) : $state)
                                ->dehydrateStateUsing(fn ($state, Get $get) => self::minorOrNull($state, $get('currency')))
                                ->rule(self::moneyRule(required: false))
                                ->helperText('Shown crossed out when higher than the price.'),
                        ])
                        ->minItems(1)
                        ->maxItems(count(Money::SUPPORTED))
                        ->defaultItems(1)
                        ->addActionLabel('Add a price in another currency'),
                ]),
            Section::make('Publishing')
                ->columnSpan(1)
                ->schema([
                    Radio::make('status')->options([Product::STATUS_DRAFT => 'Draft (not visible)', Product::STATUS_PUBLISHED => 'Published'])
                        ->default(Product::STATUS_DRAFT)->required(),
                    DateTimePicker::make('published_at')->label('Publish time')->seconds(false)
                        ->helperText('Leave empty to publish now. A future time schedules it.'),
                    Toggle::make('is_featured')->label('Feature at the top of the store'),
                    Fields::mediaById('cover_media_id', 'Cover image'),
                    Select::make('preview_media_id')
                        ->label('Free sample (optional)')
                        ->relationship('preview', 'filename')
                        ->getOptionLabelFromRecordUsing(fn (MediaAsset $m): string => ($m->title ?: ($m->alt_text ?: $m->filename)).($m->isImage() ? '' : ' (PDF)'))
                        ->searchable(['title', 'filename', 'alt_text'])
                        ->preload()
                        ->helperText('A public image or PDF from the media library, e.g. the first chapter.'),
                    Select::make('categories')
                        ->relationship('categories', 'name', fn (Builder $query) => $query->where('type', Category::TYPE_PRODUCT))
                        ->multiple()
                        ->preload()
                        ->createOptionForm([TextInput::make('name')->required()->maxLength(80)])
                        ->createOptionUsing(fn (array $data): int => Category::query()->firstOrCreate(
                            ['type' => Category::TYPE_PRODUCT, 'slug' => Str::limit(Str::slug((string) $data['name']), 100, '') ?: 'category'],
                            ['name' => trim((string) $data['name'])],
                        )->getKey()),
                    Select::make('tags')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->preload()
                        ->createOptionForm([TextInput::make('name')->required()->maxLength(60)])
                        ->createOptionUsing(fn (array $data): int => Tag::query()->firstOrCreate(
                            ['slug' => Str::limit(Str::slug((string) $data['name']), 80, '') ?: 'tag'],
                            ['name' => trim((string) $data['name'])],
                        )->getKey()),
                ]),
            Section::make('Search & sharing')
                ->columnSpan(1)
                ->collapsed()
                ->schema([
                    TextInput::make('seo.title')->label('Search title')->maxLength(70),
                    Textarea::make('seo.description')->label('Search description')->rows(2)->maxLength(170),
                    Toggle::make('seo.noindex')->label('Hide from search engines'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            // Filament passes the query by parameter name: it must be called $query.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('prices'))
            ->columns([
                TextColumn::make('title')->searchable()->limit(50)->description(fn (Product $r) => '/store/'.$r->slug),
                TextColumn::make('type')->badge()->formatStateUsing(fn (ProductType $state) => $state->label()),
                TextColumn::make('price')->label('Price')
                    ->getStateUsing(fn (Product $r): string => $r->is_free
                        ? 'Free'
                        : ($r->prices->map(fn ($p) => Money::format($p->amount, $p->currency))->implode(' · ') ?: '—')),
                TextColumn::make('status')->badge()
                    ->getStateUsing(fn (Product $r): string => match (true) {
                        $r->trashed() => 'In trash',
                        $r->status !== Product::STATUS_PUBLISHED => 'Draft',
                        $r->published_at !== null && $r->published_at->isFuture() => 'Scheduled',
                        default => 'Published',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Published' => 'success', 'Scheduled' => 'info', 'In trash' => 'danger', default => 'gray',
                    }),
                IconColumn::make('is_featured')->label('Featured')->boolean(),
                TextColumn::make('files_count')->counts('files')->label('Files'),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(ProductType::storeCreatable()),
                SelectFilter::make('status')->options([Product::STATUS_DRAFT => 'Draft', Product::STATUS_PUBLISHED => 'Published']),
                TrashedFilter::make(),
            ])
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Add a product, set its price and upload the files buyers receive.')
            ->recordActions([EditAction::make()]);
    }

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [FilesRelationManager::class, VersionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    /**
     * Typed amount → minor units. Empty or invalid input becomes null; the validation rule below
     * reports the problem to the editor, so this never interrupts the form.
     */
    private static function minorOrNull(mixed $state, mixed $currency): ?int
    {
        if (blank($state)) {
            return null;
        }

        try {
            return Money::toMinor((string) $state, is_string($currency) && $currency !== '' ? $currency : 'NGN');
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** Amounts like 12500 or 12,500.50; a price must be more than zero. */
    private static function moneyRule(bool $required): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($required): void {
            if (blank($value)) {
                if ($required) {
                    $fail('Enter a price.');
                }

                return;
            }

            try {
                $minor = Money::toMinor((string) $value, 'NGN');
            } catch (InvalidArgumentException $e) {
                $fail($e->getMessage());

                return;
            }

            if ($required && $minor <= 0) {
                $fail('A price must be more than zero. Use "Free product" for free items.');
            }
        };
    }
}
