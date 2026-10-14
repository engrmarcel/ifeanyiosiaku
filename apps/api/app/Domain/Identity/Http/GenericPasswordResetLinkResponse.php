<?php

namespace App\Domain\Identity\Http;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Same response whether or not the email belongs to an account (or the request was throttled),
 * so the "forgot password" form cannot be used to discover who is registered.
 */
class GenericPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'If an account exists for that email address, a password reset link is on its way.',
        ], 200);
    }
}
