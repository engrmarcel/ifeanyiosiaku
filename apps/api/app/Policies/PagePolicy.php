<?php

namespace App\Policies;

use App\Domain\Cms\Models\Page;
use App\Domain\Identity\Enums\Permission;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function view(User $actor, Page $page): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function update(User $actor, Page $page): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function publish(User $actor, Page $page): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    /** Soft delete only; the homepage must be replaced first. */
    public function delete(User $actor, Page $page): bool
    {
        return $actor->can(Permission::CmsPublish->value) && ! $page->is_homepage;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Page $page): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function forceDelete(User $actor, Page $page): bool
    {
        return false;
    }
}
