<?php

namespace App\Domain\Identity\Http;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of any account that is no longer active, on every web/API request.
 * Defence in depth: suspension also deletes the user's stored sessions.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isActive()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if (! $request->expectsJson()) {
                return redirect()->away(config('platform.frontend_url').'/login?reason=account_inactive');
            }

            return response()->json([
                'error' => [
                    'code' => 'account_suspended',
                    'message' => 'This account is not active. Please contact support.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
