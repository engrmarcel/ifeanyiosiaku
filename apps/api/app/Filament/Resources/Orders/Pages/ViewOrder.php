<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Orders\OrderTransitions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Cancel order')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('The customer has not paid. Cancelling frees any coupon it used. Paid orders are refunded instead (coming in a later step).')
                ->visible(function (Order $record): bool {
                    $user = Auth::user();

                    return $record->status === OrderStatus::Pending && $user instanceof User && $user->can('cancel', $record);
                })
                ->action(function (Order $record) {
                    $user = Auth::user();
                    app(OrderTransitions::class)->cancel($record, 'admin', $user instanceof User ? $user : null);
                    Notification::make()->title("Order {$record->number} cancelled")->success()->send();
                }),
        ];
    }
}
