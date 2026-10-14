<?php

namespace App\Domain\Identity\Http;

use App\Domain\Settings\SettingsRepository;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff must enrol in two-factor authentication before using the admin panel.
 * They are redirected to the website's security page to set it up.
 */
class RequireStaffTwoFactor
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && $this->settings->bool('security.require_staff_two_factor')
            && ! $user->hasConfirmedTwoFactor()) {
            $target = config('platform.frontend_url').'/account/security?setup=two-factor&next=/admin';

            return $request->expectsJson()
                ? response()->json(['error' => ['code' => 'two_factor_required', 'message' => 'Two-factor authentication is required for staff accounts.']], 403)
                : redirect()->away($target);
        }

        return $next($request);
    }
}
