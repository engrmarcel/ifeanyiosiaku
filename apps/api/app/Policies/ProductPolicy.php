<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Settings\Addons;
use App\Domain\Settings\Enums\Addon;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Store catalogue (products and their prices, versions and files). Needs the commerce permission,
 * and the Commerce add-on switched on (ADR-025): while it is off the store screens are hidden.
 */
class ProductPolicy
{
    private function allowed(User $actor): bool
    {
        return app(Addons::class)->enabled(Addon::Commerce) && $actor->can(Permission::CommerceManage->value);
    }

    public function viewAny(User $actor): bool
    {
        return $this->allowed($actor);
    }

    public function view(User $actor, Model $model): bool
    {
        return $this->allowed($actor);
    }

    public function create(User $actor): bool
    {
        return $this->allowed($actor);
    }

    public function update(User $actor, Model $model): bool
    {
        return $this->allowed($actor);
    }

    public function delete(User $actor, Model $model): bool
    {
        return $this->allowed($actor);
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Model $model): bool
    {
        return $this->allowed($actor);
    }

    public function forceDelete(User $actor, Model $model): bool
    {
        return false;
    }
}
