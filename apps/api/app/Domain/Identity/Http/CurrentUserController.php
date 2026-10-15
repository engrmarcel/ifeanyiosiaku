<?php

namespace App\Domain\Identity\Http;

use App\Domain\Settings\SettingsRepository;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class CurrentUserController
{
    public function __invoke(#[CurrentUser] User $user, SettingsRepository $settings): JsonResponse
    {
        $user->load('profile');

        return response()->json(['data' => [
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
        ]]);
    }
}
