<?php

namespace App\Policies;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Enums\Permission;
use App\Models\User;

/** Audit entries are read-only for everyone. */
class AuditLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::AuditLogView->value);
    }

    public function view(User $actor, AuditLog $entry): bool
    {
        return $actor->can(Permission::AuditLogView->value);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, AuditLog $entry): bool
    {
        return false;
    }

    public function delete(User $actor, AuditLog $entry): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
