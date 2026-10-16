<?php

namespace App\Policies;

use App\Domain\Content\Models\Article;
use App\Domain\Identity\Enums\Permission;
use App\Models\User;

/** Content staff write articles; publishing (and deleting) needs the publish permission. */
class ArticlePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function view(User $actor, Article $article): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::CmsManage->value);
    }

    public function update(User $actor, Article $article): bool
    {
        // A published article changes the live site, so editing it needs the publish permission too.
        return $actor->can(Permission::CmsManage->value)
            && ($article->status !== Article::STATUS_PUBLISHED || $actor->can(Permission::CmsPublish->value));
    }

    public function publish(User $actor): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function delete(User $actor, Article $article): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Article $article): bool
    {
        return $actor->can(Permission::CmsPublish->value);
    }

    public function forceDelete(User $actor, Article $article): bool
    {
        return false;
    }
}
