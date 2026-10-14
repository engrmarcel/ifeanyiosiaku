<?php

namespace App\Domain\Identity\Http;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Actions\RevokeUserSessions;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lets a member see where they are signed in and sign out other devices.
 * Raw session IDs are never returned; each session is identified by a keyed hash.
 */
class SessionController
{
    public function __construct(
        private readonly RevokeUserSessions $revokeSessions,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $currentId = $request->session()->getId();

        $sessions = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->limit(50)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session) => [
                'id' => $this->publicId($session->id),
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_active_at' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                'is_current' => hash_equals($currentId, $session->id),
            ]);

        return response()->json(['data' => $sessions]);
    }

    public function destroy(Request $request, #[CurrentUser] User $user, string $session): JsonResponse
    {
        $table = DB::table(config('session.table', 'sessions'));
        $match = $table->clone()
            ->where('user_id', $user->getKey())
            ->pluck('id')
            ->first(fn (string $id) => hash_equals($this->publicId($id), $session));

        abort_if($match === null, 404);
        abort_if($match === $request->session()->getId(), 422, 'Use sign out to end the current session.');

        $table->where('id', $match)->delete();
        $this->audit->record('auth.session_revoked', $user);

        return response()->json(null, 204);
    }

    public function destroyOthers(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password:web']]);

        Auth::guard('web')->logoutOtherDevices($request->input('password'));
        $count = $this->revokeSessions->handle($user, $request->session()->getId());
        $this->audit->record('auth.other_sessions_revoked', $user, ['count' => $count]);

        return response()->json(['data' => ['revoked' => $count]]);
    }

    private function publicId(string $sessionId): string
    {
        return substr(hash_hmac('sha256', $sessionId, (string) config('app.key')), 0, 32);
    }
}
