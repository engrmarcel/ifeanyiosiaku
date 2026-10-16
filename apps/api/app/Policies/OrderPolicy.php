<?php

namespace App\Policies;

use App\Domain\Commerce\Models\Order;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Settings\Addons;
use App\Domain\Settings\Enums\Addon;
use App\Models\User;

/**
 * Orders in the admin panel: viewing needs orders.view (Admins, Finance); cancelling an unpaid
 * order needs orders.manage. Orders are financial records and are never deleted or edited.
 */
class OrderPolicy
{
    private function on(): bool
    {
        return app(Addons::class)->enabled(Addon::Commerce);
    }

    public function viewAny(User $actor): bool
    {
        return $this->on() && $actor->can(Permission::OrdersView->value);
    }

    public function view(User $actor, Order $order): bool
    {
        return $this->on() && $actor->can(Permission::OrdersView->value);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Order $order): bool
    {
        return false;
    }

    public function cancel(User $actor, Order $order): bool
    {
        return $this->on() && $actor->can(Permission::OrdersManage->value);
    }

    public function delete(User $actor, Order $order): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
