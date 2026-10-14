<?php

use App\Domain\Captcha\Http\CaptchaController;
use App\Domain\Identity\Http\CurrentUserController;
use App\Domain\Identity\Http\SessionController;
use App\Domain\Settings\Http\PublicSettingsController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
| Versioned JSON API, registered in bootstrap/app.php with the 'web' middleware group: the website
| calls it same-origin with the session cookie and an X-XSRF-TOKEN header (ADR-003).
| Authentication endpoints (login, register, 2FA...) are registered by Fortify under /api/v1/auth.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Any GET through the web group sets the XSRF-TOKEN cookie; the website calls this before forms.
    Route::get('csrf-cookie', fn () => response()->noContent())->name('csrf-cookie');
    Route::get('health', HealthController::class)->name('health');
    Route::get('settings/public', PublicSettingsController::class)->name('settings.public');

    Route::prefix('captcha')->name('captcha.')->group(function () {
        Route::get('config', [CaptchaController::class, 'config'])->name('config');
        Route::post('challenge', [CaptchaController::class, 'challenge'])
            ->middleware('throttle:captcha')
            ->name('challenge');
    });

    Route::middleware(['auth'])->group(function () {
        Route::get('me', CurrentUserController::class)->name('me');

        Route::prefix('account/sessions')->name('account.sessions.')->group(function () {
            Route::get('/', [SessionController::class, 'index'])->name('index');
            Route::post('revoke-others', [SessionController::class, 'destroyOthers'])
                ->middleware('throttle:6,1')
                ->name('revoke-others');
            Route::delete('{session}', [SessionController::class, 'destroy'])
                ->where('session', '[a-f0-9]{32}')
                ->name('destroy');
        });
    });
});
