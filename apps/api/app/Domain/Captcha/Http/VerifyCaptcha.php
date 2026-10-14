<?php

namespace App\Domain\Captcha\Http;

use App\Domain\Captcha\CaptchaManager;
use App\Domain\Captcha\CaptchaResult;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces CAPTCHA on abuse-prone form submissions.
 *
 * Usage: ->middleware('captcha:contact') on own routes. Fortify's routes (registered by the package)
 * are protected through FORTIFY_ROUTES, keyed by route name.
 */
class VerifyCaptcha
{
    public const TOKEN_FIELD = 'captcha_token';

    /** @var array<string, string> Fortify route name => CAPTCHA purpose */
    private const FORTIFY_ROUTES = [
        'register.store' => 'register',
        'password.email' => 'password_reset',
        'login.store' => 'login',
    ];

    /** Login requires a CAPTCHA only after this many recent failures from the same IP. */
    public const LOGIN_FAILURES_BEFORE_CAPTCHA = 3;

    public function __construct(private readonly CaptchaManager $captcha) {}

    public function handle(Request $request, Closure $next, ?string $purpose = null): Response
    {
        $purpose ??= self::FORTIFY_ROUTES[$request->route()?->getName() ?? ''] ?? null;

        if ($purpose === null || ! $request->isMethod('POST')) {
            return $next($request);
        }

        if ($purpose === 'login' && ! self::loginRequiresCaptcha($request)) {
            return $next($request);
        }

        if (filled($request->input(config('captcha.honeypot_field')))) {
            return $this->reject($request, $purpose, 'honeypot');
        }

        $result = $this->captcha->driver()->verify(
            $request->input(self::TOKEN_FIELD),
            $purpose,
            $request,
        );

        if (! $result->passed) {
            return $this->reject($request, $purpose, (string) $result->reason);
        }

        return $next($request);
    }

    public static function loginFailureKey(Request $request): string
    {
        return 'login-failures:'.sha1((string) $request->ip());
    }

    public static function loginRequiresCaptcha(Request $request): bool
    {
        return RateLimiter::attempts(self::loginFailureKey($request)) >= self::LOGIN_FAILURES_BEFORE_CAPTCHA;
    }

    private function reject(Request $request, string $purpose, string $reason): Response
    {
        Log::notice('CAPTCHA rejected a submission.', [
            'purpose' => $purpose,
            'reason' => $reason,
            'ip_hash' => sha1((string) $request->ip()),
        ]);

        $unavailable = $reason === CaptchaResult::UNAVAILABLE;

        return response()->json([
            'error' => [
                'code' => $unavailable ? 'captcha_unavailable' : 'captcha_failed',
                'message' => $unavailable
                    ? 'Verification is temporarily unavailable. Please try again shortly.'
                    : 'We could not verify this submission. Please try again.',
                'reason' => $reason,
                'fields' => [self::TOKEN_FIELD => ['Verification failed.']],
            ],
        ], $unavailable ? 503 : 422);
    }
}
