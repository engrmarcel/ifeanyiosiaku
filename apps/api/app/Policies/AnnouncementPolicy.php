<?php

namespace App\Policies;

use App\Domain\Announcements\Models\Announcement;
use App\Domain\Identity\Enums\Permission;
use App\Models\User;

/** Announcements appear to every visitor, so changing them needs the publish permission. */
class AnnouncementPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function view(User $actor, Announcement $announcement): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function update(User $actor, Announcement $announcement): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function delete(User $actor, Announcement $announcement): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
