<?php

namespace App\Domain\Commerce\Console;

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Orders\OrderTransitions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('commerce:expire-orders')]
#[Description('Mark orders that were not paid within the payment window as expired (frees their coupons)')]
class ExpireOrders extends Command
{
    public function handle(OrderTransitions $transitions): int
    {
        $count = 0;

        Order::query()->where('status', OrderStatus::Pending)->where('expires_at', '<', now())
            ->orderBy('id')->chunkById(200, function ($orders) use ($transitions, &$count) {
                foreach ($orders as $order) {
                    $count += $transitions->expire($order) ? 1 : 0;
                }
            });

        if ($count > 0) {
            $this->components->info("Expired {$count} unpaid order(s).");
        }

        return self::SUCCESS;
    }
}
