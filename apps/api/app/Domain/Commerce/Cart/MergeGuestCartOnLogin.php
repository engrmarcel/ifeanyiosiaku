<?php

namespace App\Domain\Commerce\Cart;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Throwable;

/** What a visitor put in their cart is kept when they sign in. */
class MergeGuestCartOnLogin
{
    public function __construct(private readonly CartService $carts) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        // A cart problem must never stop someone signing in.
        try {
            $this->carts->mergeIntoUser(request(), $event->user);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
