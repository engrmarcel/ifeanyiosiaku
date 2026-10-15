<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::MediaManage->value);
    }

    public function view(User $actor, MediaAsset $asset): bool
    {
        return $actor->can(Permission::MediaManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::MediaManage->value);
    }

    public function update(User $actor, MediaAsset $asset): bool
    {
        return $actor->can(Permission::MediaManage->value);
    }

    /** In-use files cannot be deleted (DeleteMediaAsset also enforces this). */
    public function delete(User $actor, MediaAsset $asset): bool
    {
        return $actor->can(Permission::MediaManage->value) && ! $asset->usages()->exists();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
