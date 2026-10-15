<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Shared rules for portfolio content (projects, skills, services, experience, ...). */
class PortfolioPolicy
{
    private function allowed(User $actor): bool
    {
        return $actor->can(Permission::PortfolioManage->value);
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
        return $this->allowed($actor);
    }

    public function reorder(User $actor): bool
    {
        return $this->allowed($actor);
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
