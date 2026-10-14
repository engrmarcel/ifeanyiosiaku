<?php

use App\Domain\Captcha\Http\VerifyCaptcha;
use App\Domain\Identity\Http\EnsureAccountIsActive;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'throttle:api'])
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            EnsureAccountIsActive::class,
            VerifyCaptcha::class,
        ]);

        // Suspended accounts must be signed out before any authentication/authorisation check runs.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureAccountIsActive::class,
        );

        $middleware->alias([
            'captcha' => VerifyCaptcha::class,
        ]);

        // Signed-out visitors are sent to the website's login page (the admin panel has no login of its own).
        $middleware->redirectGuestsTo(fn (Request $request) => config('platform.frontend_url').'/login?'.http_build_query([
            'next' => $request->getRequestUri(),
        ]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport([TokenMismatchException::class]);

        // Consistent JSON error envelope for the API (SYSTEM_ARCHITECTURE §8).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $code, $message, $fields] = match (true) {
                $e instanceof ValidationException => [$e->status, 'validation_failed', 'The given data was invalid.', $e->errors()],
                $e instanceof AuthenticationException => [401, 'unauthenticated', 'Please sign in to continue.', null],
                $e instanceof AuthorizationException => [403, 'forbidden', 'You do not have permission to do that.', null],
                $e instanceof ModelNotFoundException => [404, 'not_found', 'The requested resource was not found.', null],
                $e instanceof TokenMismatchException => [419, 'csrf_token_mismatch', 'Your session expired. Please refresh and try again.', null],
                $e instanceof ThrottleRequestsException => [429, 'too_many_requests', 'Too many requests. Please slow down.', null],
                $e instanceof HttpExceptionInterface => [
                    $e->getStatusCode(),
                    match ($e->getStatusCode()) {
                        403 => 'forbidden', 404 => 'not_found', 405 => 'method_not_allowed',
                        419 => 'csrf_token_mismatch', 422 => 'unprocessable', 429 => 'too_many_requests',
                        default => 'http_error',
                    },
                    $e->getMessage() ?: 'Request could not be completed.',
                    null,
                ],
                default => [500, 'server_error', 'Something went wrong on our side.', null],
            };

            $error = ['code' => $code, 'message' => $message];

            if ($fields !== null) {
                $error['fields'] = $fields;
            }

            if ($status >= 500 && config('app.debug')) {
                $error['debug'] = ['exception' => $e::class, 'message' => $e->getMessage()];
            }

            $error['request_id'] = Context::get('request_id');

            $response = response()->json(['error' => $error], $status);

            if ($e instanceof HttpExceptionInterface) {
                $response->withHeaders($e->getHeaders());
            }

            return $response;
        });
    })->create();
