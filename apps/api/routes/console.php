<?php

use App\Domain\Announcements\Models\AnnouncementEvent;
use App\Domain\Captcha\Models\CaptchaChallenge;
use App\Domain\Commerce\Models\Cart;
use App\Domain\Contact\Models\ContactMessage;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Lets the health check confirm that cron is running `php artisan schedule:run` every minute.
Schedule::call(fn () => Cache::put(HealthController::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay()))
    ->name('scheduler-heartbeat')
    ->everyMinute();

Schedule::command('cms:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('content:announce-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('commerce:expire-orders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [Cart::class]])->daily();
Schedule::command('model:prune', ['--model' => [CaptchaChallenge::class]])->hourly();
Schedule::command('model:prune', ['--model' => [AnnouncementEvent::class, ContactMessage::class]])->daily();
Schedule::command('auth:clear-resets')->daily();
Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])->daily();
