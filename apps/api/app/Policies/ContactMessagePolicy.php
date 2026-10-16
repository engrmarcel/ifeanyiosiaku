<?php

namespace App\Policies;

use App\Domain\Contact\Models\ContactMessage;
use App\Domain\Identity\Enums\Permission;
use App\Models\User;

/** Contact messages contain personal data: only staff who manage people or content may read them. */
class ContactMessagePolicy
{
    private function allowed(User $actor): bool
    {
        return $actor->can(Permission::ContactManage->value);
    }

    public function viewAny(User $actor): bool
    {
        return $this->allowed($actor);
    }

    public function view(User $actor, ContactMessage $message): bool
    {
        return $this->allowed($actor);
    }

    public function create(User $actor): bool
    {
        return false; // messages only arrive through the website form
    }

    public function update(User $actor, ContactMessage $message): bool
    {
        return $this->allowed($actor);
    }

    public function delete(User $actor, ContactMessage $message): bool
    {
        return $this->allowed($actor);
    }

    public function deleteAny(User $actor): bool
    {
        return $this->allowed($actor);
    }
}
