<?php

namespace App\Policies;

use App\Domain\Cms\Models\Redirect;
use App\Domain\Identity\Enums\Permission;
use App\Models\User;

/** Redirects change live addresses immediately, so editing them needs the publish permission. */
class RedirectPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function view(User $actor, Redirect $redirect): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function update(User $actor, Redirect $redirect): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function delete(User $actor, Redirect $redirect): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
