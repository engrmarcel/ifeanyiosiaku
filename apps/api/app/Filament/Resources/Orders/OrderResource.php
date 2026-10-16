<?php

namespace App\Filament\Resources\Orders;

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\OrderItem;
use App\Domain\Commerce\Money;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Orders are financial records: they can be viewed (and unpaid ones cancelled), never edited or deleted. */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'number';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')
                ->columns(3)
                ->schema([
                    TextEntry::make('number')->copyable(),
                    TextEntry::make('status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())
                        ->color(fn (OrderStatus $state) => self::color($state)),
                    TextEntry::make('created_at')->label('Placed')->dateTime(),
                    TextEntry::make('billing_name')->label('Customer'),
                    TextEntry::make('billing_email')->label('Email')->copyable(),
                    TextEntry::make('paid_at')->label('Paid')->dateTime()->placeholder('—'),
                    TextEntry::make('subtotal_amount')->label('Subtotal')->formatStateUsing(fn (int $state, Order $record) => Money::format($state, $record->currency)),
                    TextEntry::make('discount_amount')->label('Discount')
                        ->formatStateUsing(fn (int $state, Order $record) => Money::format($state, $record->currency).($record->coupon_code ? " ({$record->coupon_code})" : '')),
                    TextEntry::make('total_amount')->label('Total')->weight('bold')->formatStateUsing(fn (int $state, Order $record) => Money::format($state, $record->currency)),
                    TextEntry::make('cancel_reason')->label('Cancelled because')->placeholder('—')
                        ->formatStateUsing(fn (?string $state) => match ($state) {
                            'customer' => 'Cancelled by the customer', 'expired' => 'Not paid in time', 'admin' => 'Cancelled by staff', default => $state
                        }),
                ]),
            Section::make('Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('product_snapshot.title')->label('Product'),
                            TextEntry::make('unit_amount')->label('Price')->formatStateUsing(fn (int $state, OrderItem $record) => Money::format($state, $record->order->currency)),
                            TextEntry::make('discount_amount')->label('Discount')->formatStateUsing(fn (int $state, OrderItem $record) => Money::format($state, $record->order->currency)),
                            TextEntry::make('total_amount')->label('Total')->formatStateUsing(fn (int $state, OrderItem $record) => Money::format($state, $record->order->currency)),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('items'))
            ->columns([
                TextColumn::make('number')->searchable()->fontFamily('mono'),
                TextColumn::make('billing_name')->label('Customer')->searchable()->description(fn (Order $r) => $r->billing_email),
                TextColumn::make('status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => self::color($state)),
                TextColumn::make('items_count')->label('Items'),
                TextColumn::make('total_amount')->label('Total')->formatStateUsing(fn (int $state, Order $r) => Money::format($state, $r->currency))->sortable(),
                TextColumn::make('created_at')->label('Placed')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])->all()),
            ])
            ->emptyStateHeading('No orders yet')
            ->recordActions([ViewAction::make()]);
    }

    public static function color(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Paid => 'success',
            OrderStatus::Pending => 'warning',
            OrderStatus::Refunded, OrderStatus::PartiallyRefunded => 'info',
            default => 'gray',
        };
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
