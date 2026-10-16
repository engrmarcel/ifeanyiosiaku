<?php

namespace App\Domain\Identity\Http;

use App\Domain\Settings\SettingsRepository;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController
{
    public function __invoke(#[CurrentUser] User $user, SettingsRepository $settings): JsonResponse
    {
        return response()->json(['data' => self::present($user, $settings)]);
    }

    /**
     * The signed-in user, or null for visitors. Public, so the website can check on every page
     * without the browser logging an "Unauthorized" error for each visitor.
     */
    public function state(Request $request, SettingsRepository $settings): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $user instanceof User ? self::present($user, $settings) : null])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(User $user, SettingsRepository $settings): array
    {
        $user->load('profile');

        return [
            'id' => $user->ulid,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'timezone' => $user->timezone ?? config('platform.display_timezone'),
            'two_factor_enabled' => $user->hasConfirmedTwoFactor(),
            'is_staff' => $user->isStaff(),
            // True when this account must enrol in 2FA before the admin panel opens (ADR-026).
            'two_factor_required' => $user->isStaff() && $settings->bool('security.require_staff_two_factor'),
            'can_access_admin' => $user->canAccessPanel(filament()->getDefaultPanel()),
            'roles' => $user->getRoleNames()->values(),
            'profile' => $user->profile ? [
                'display_name' => $user->profile->display_name,
                'handle' => $user->profile->handle,
                'visibility' => $user->profile->visibility->value,
                'show_on_leaderboard' => $user->profile->show_on_leaderboard,
            ] : null,
        ];
    }
}
