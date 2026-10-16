<?php

namespace App\Filament\Resources\Coupons;

use App\Domain\Commerce\Models\Coupon;
use App\Domain\Commerce\Models\Product;
use App\Domain\Commerce\Money;
use App\Domain\Commerce\Store;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use UnitEnum;

/** Discount codes. Usage counts only include orders that were not cancelled or left to expire. */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')
                ->required()
                ->maxLength(40)
                ->regex('/^[A-Za-z0-9\-_]+$/')
                ->unique(ignoreRecord: true)
                ->helperText('Letters, numbers, hyphens. Customers can type it in any case.'),
            TextInput::make('description')->maxLength(200)->placeholder('Launch week'),
            Radio::make('type')->options([Coupon::PERCENT => 'Percentage off', Coupon::FIXED => 'Fixed amount off'])->default(Coupon::PERCENT)->required()->live(),
            TextInput::make('value')
                ->label(fn (Get $get) => $get('type') === Coupon::FIXED ? 'Amount off' : 'Percent off')
                ->required()
                ->formatStateUsing(fn ($state, Get $get) => $get('type') === Coupon::FIXED && is_numeric($state) ? Money::toMajor((int) $state, (string) ($get('currency') ?: 'NGN')) : $state)
                ->dehydrateStateUsing(function ($state, Get $get) {
                    if ($get('type') !== Coupon::FIXED) {
                        return (int) $state;
                    }
                    try {
                        return Money::toMinor((string) $state, (string) ($get('currency') ?: 'NGN'));
                    } catch (InvalidArgumentException) {
                        return null;
                    }
                })
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    if ($get('type') === Coupon::FIXED) {
                        try {
                            if (Money::toMinor((string) $value, 'NGN') <= 0) {
                                $fail('Enter an amount above zero.');
                            }
                        } catch (InvalidArgumentException $e) {
                            $fail($e->getMessage());
                        }
                    } elseif (! ctype_digit((string) $value) || (int) $value < 1 || (int) $value > 100) {
                        $fail('Enter a whole percentage from 1 to 100.');
                    }
                }),
            Select::make('currency')
                ->options(fn () => array_intersect_key(Money::SUPPORTED, array_flip(app(Store::class)->currencies())))
                ->default(fn () => app(Store::class)->defaultCurrency())
                ->visible(fn (Get $get) => $get('type') === Coupon::FIXED)
                ->required(fn (Get $get) => $get('type') === Coupon::FIXED),
            Select::make('applies_to')
                ->label('Only for these products (optional)')
                ->multiple()
                ->options(fn () => Product::query()->orderBy('title')->pluck('title', 'id')->all())
                ->searchable()
                ->helperText('Leave empty to apply to every product.'),
            TextInput::make('min_subtotal')
                ->label('Minimum spend (optional)')
                ->formatStateUsing(fn ($state) => is_numeric($state) ? Money::toMajor((int) $state, 'NGN') : $state)
                ->dehydrateStateUsing(function ($state) {
                    try {
                        return filled($state) ? Money::toMinor((string) $state, 'NGN') : null;
                    } catch (InvalidArgumentException) {
                        return null;
                    }
                })
                ->helperText('In the order currency, on the products the code applies to.'),
            TextInput::make('max_uses')->label('Total uses allowed')->numeric()->minValue(1)->placeholder('Unlimited'),
            TextInput::make('max_uses_per_user')->label('Uses per customer')->numeric()->minValue(1)->placeholder('Unlimited'),
            DateTimePicker::make('starts_at')->label('Valid from')->seconds(false),
            DateTimePicker::make('ends_at')->label('Valid until')->seconds(false)->after('starts_at'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['redemptions as uses' => fn ($r) => $r->whereNull('released_at')]))
            ->columns([
                TextColumn::make('code')->searchable()->fontFamily('mono')->description(fn (Coupon $r) => $r->description),
                TextColumn::make('value')->label('Discount')
                    ->formatStateUsing(fn (int $state, Coupon $r) => $r->type === Coupon::FIXED ? Money::format($state, (string) $r->currency).' off' : "{$state}% off"),
                TextColumn::make('uses')->label('Used')
                    ->formatStateUsing(fn ($state, Coupon $r) => $r->max_uses ? "{$state} / {$r->max_uses}" : (string) $state),
                TextColumn::make('ends_at')->label('Until')->dateTime()->placeholder('No end date'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->emptyStateHeading('No coupons yet')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoupons::route('/')];
    }
}
