<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Newsletter\Models\NewsletterSubscriber;
use App\Models\User;

/** Subscribers join only through double opt-in, so staff can view, export and remove but not add. */
class NewsletterSubscriberPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::NewsletterManage->value);
    }

    public function view(User $actor, NewsletterSubscriber $subscriber): bool
    {
        return $actor->can(Permission::NewsletterManage->value);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, NewsletterSubscriber $subscriber): bool
    {
        return false;
    }

    public function delete(User $actor, NewsletterSubscriber $subscriber): bool
    {
        return $actor->can(Permission::NewsletterManage->value);
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
