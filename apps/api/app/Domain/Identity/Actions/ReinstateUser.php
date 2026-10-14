<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;

class ReinstateUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, User $actor): void
    {
        $user->forceFill([
            'status' => UserStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();

        $this->audit->record('user.reinstated', $user, actor: $actor);
    }
}
