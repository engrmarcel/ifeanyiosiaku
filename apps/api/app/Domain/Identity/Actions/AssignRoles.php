<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes a user's roles. Only a Super Administrator may grant or remove the Super Administrator
 * role, and the last Super Administrator can never be demoted.
 */
class AssignRoles
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RevokeUserSessions $revokeSessions,
    ) {}

    /**
     * @param  list<string>  $roles
     */
    public function handle(User $user, array $roles, User $actor): void
    {
        $roles = array_values(array_unique(array_filter($roles, fn (string $role) => Role::tryFrom($role) !== null)));
        $before = $user->getRoleNames()->values()->all();
        $super = Role::SuperAdmin->value;
        $touchesSuper = in_array($super, $before, true) !== in_array($super, $roles, true);

        if ($touchesSuper && ! $actor->hasRole($super)) {
            throw ValidationException::withMessages(['roles' => 'Only a Super Administrator can grant or remove the Super Administrator role.']);
        }

        if (in_array($super, $before, true) && ! in_array($super, $roles, true)
            && User::role($super)->count() <= 1) {
            throw ValidationException::withMessages(['roles' => 'The last Super Administrator cannot be removed.']);
        }

        DB::transaction(function () use ($user, $roles, $before, $actor) {
            $user->syncRoles($roles);

            // Losing a staff role must take effect immediately, including in open admin sessions.
            $lostStaffAccess = collect($before)->contains(fn (string $r) => Role::from($r)->isStaff())
                && ! collect($roles)->contains(fn (string $r) => Role::from($r)->isStaff());

            if ($lostStaffAccess && ! $user->is($actor)) {
                $this->revokeSessions->handle($user);
            }

            $this->audit->record('user.roles_changed', $user, ['from' => $before, 'to' => $roles], $actor);
        });
    }
}
