<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Suspends an account and immediately revokes all of its sessions (and therefore admin access).
 */
class SuspendUser
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RevokeUserSessions $revokeSessions,
    ) {}

    public function handle(User $user, string $reason, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => 'You cannot suspend your own account.']);
        }

        if ($user->hasRole(Role::SuperAdmin->value) && ! $actor->hasRole(Role::SuperAdmin->value)) {
            throw ValidationException::withMessages(['user' => 'Only a Super Administrator can suspend another Super Administrator.']);
        }

        DB::transaction(function () use ($user, $reason, $actor) {
            $user->forceFill([
                'status' => UserStatus::Suspended,
                'suspended_at' => now(),
                'suspension_reason' => Str::limit($reason, 490),
                'remember_token' => Str::random(60),
            ])->save();

            $this->revokeSessions->handle($user);

            $this->audit->record('user.suspended', $user, ['reason' => $reason], $actor);
        });
    }
}
