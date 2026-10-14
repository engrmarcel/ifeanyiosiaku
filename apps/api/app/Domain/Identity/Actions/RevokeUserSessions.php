<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes server-side sessions for a user. Requires the database session driver (ADR-009 note:
 * sessions stay in the database in every environment so they can be listed and revoked).
 */
class RevokeUserSessions
{
    public function handle(User $user, ?string $exceptSessionId = null): int
    {
        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }
}
