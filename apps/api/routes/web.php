<?php

use Illuminate\Support\Facades\Route;

// The public website is the Next.js app. Anyone reaching Laravel's root directly is sent there.
Route::get('/', fn () => redirect()->away(config('platform.frontend_url')));
