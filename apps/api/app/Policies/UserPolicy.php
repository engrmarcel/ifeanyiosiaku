<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersView->value);
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can(Permission::UsersView->value);
    }

    /** Accounts are created by registration or the console command, never from the admin panel. */
    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function suspend(User $actor, User $user): bool
    {
        return $actor->can(Permission::UsersSuspend->value) && ! $actor->is($user);
    }

    public function assignRoles(User $actor, User $user): bool
    {
        return $actor->can(Permission::RolesAssign->value);
    }

    /** Deletion is a separate, audited account-deletion workflow (financial records are retained). */
    public function delete(User $actor, User $user): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
