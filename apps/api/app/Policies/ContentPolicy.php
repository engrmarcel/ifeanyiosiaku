<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules for publications, categories and tags: content staff manage them; anything that
 * removes live content needs the publish permission.
 */
class ContentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function view(User $actor, Model $model): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function update(User $actor, Model $model): bool
    {
        // Only publications have a status; categories and tags never count as published content.
        $status = $model->getAttributes()['status'] ?? null;

        return $actor->can(Permission::CmsManage->value)
            && ($status !== 'published' || $actor->can(Permission::CmsPublish->value));
    }

    public function publish(User $actor): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function delete(User $actor, Model $model): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Model $model): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function forceDelete(User $actor, Model $model): bool
    {
        return false;
    }
}
