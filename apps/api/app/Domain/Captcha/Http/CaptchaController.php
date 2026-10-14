<?php

namespace App\Domain\Captcha\Http;

use App\Domain\Captcha\CaptchaManager;
use App\Domain\Captcha\Drivers\FirstPartyCaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaptchaController
{
    public function __construct(private readonly CaptchaManager $captcha) {}

    /** Public configuration for the active provider (never includes secret keys). */
    public function config(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                ...$this->captcha->driver()->publicConfig(),
                'token_field' => VerifyCaptcha::TOKEN_FIELD,
                'honeypot_field' => config('captcha.honeypot_field'),
                'login_requires_captcha' => VerifyCaptcha::loginRequiresCaptcha($request),
            ],
        ]);
    }

    /** Issue a first-party proof-of-work challenge. */
    public function challenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purpose' => ['required', 'string', Rule::in(config('captcha.purposes'))],
        ]);

        $driver = $this->captcha->driver();

        if (! $driver instanceof FirstPartyCaptcha) {
            return response()->json([
                'error' => [
                    'code' => 'captcha_driver_mismatch',
                    'message' => 'The active verification provider does not issue first-party challenges.',
                ],
            ], 409);
        }

        return response()->json(['data' => $driver->issue($validated['purpose'], $request)], 201);
    }
}
