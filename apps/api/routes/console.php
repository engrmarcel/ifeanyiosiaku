<?php

use App\Domain\Captcha\Models\CaptchaChallenge;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Lets the health check confirm that cron is running `php artisan schedule:run` every minute.
Schedule::call(fn () => Cache::put(HealthController::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay()))
    ->name('scheduler-heartbeat')
    ->everyMinute();

Schedule::command('model:prune', ['--model' => [CaptchaChallenge::class]])->hourly();
Schedule::command('auth:clear-resets')->daily();
Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])->daily();
