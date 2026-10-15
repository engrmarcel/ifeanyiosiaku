<?php

/*
|--------------------------------------------------------------------------
| Platform defaults
|--------------------------------------------------------------------------
|
| Fallback values for settings that administrators can change at runtime
| (stored in the `settings` table). Secrets never belong here; they are read
| from environment variables in config/services.php.
|
*/

return [

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3100'), '/'),

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Africa/Lagos'),

    'web_revalidate_secret' => env('WEB_REVALIDATE_SECRET'),

    'defaults' => [
        'site.name' => 'Ifeanyi Osiaku',
        'site.tagline' => 'Software Developer | AI Enthusiast | Business Intelligence & Data Analytics Professional',

        // The announcement popup is OFF until an administrator switches it on (REQ-ANN-1).
        'announcements.enabled' => false,

        'security.require_staff_two_factor' => true,

        'captcha.driver' => env('CAPTCHA_DRIVER', 'first_party'),

        'registration.enabled' => true,

        // OFF by default: the website discourages saving images, right-click and developer-tools shortcuts.
        // This deters casual copying only; it cannot technically prevent it (see DECISIONS ADR-024).
        'protection.allow_saving_and_inspect' => false,
    ],

    // Settings that may be exposed to the public website (never secrets).
    'public_settings' => [
        'site.name',
        'site.tagline',
        'announcements.enabled',
        'captcha.driver',
        'registration.enabled',
        'protection.allow_saving_and_inspect',
    ],

];
