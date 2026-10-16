<?php

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** An order became paid (once). Fulfilment, receipts and emails listen for this. */
class OrderPaid
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
