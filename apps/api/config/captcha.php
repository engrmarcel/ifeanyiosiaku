<?php

return [

    /*
    | Default driver when no administrator choice is stored in settings ('captcha.driver').
    | Supported: first_party, recaptcha, turnstile. The `null` driver only works in testing.
    */
    'driver' => env('CAPTCHA_DRIVER', 'first_party'),

    // Form purposes that may request and verify challenges.
    'purposes' => ['register', 'login', 'password_reset', 'contact', 'booking', 'newsletter', 'comment', 'community_post'],

    'first_party' => [
        // Required leading zero bits in SHA-256(challenge:nonce). Each +1 doubles the average work.
        'difficulty' => (int) env('CAPTCHA_DIFFICULTY', 16),
        // Extra bits added when one IP requests many challenges in a short window.
        'elevated_difficulty_bits' => 3,
        'elevated_after_challenges' => 10,
        'ttl_seconds' => (int) env('CAPTCHA_TTL_SECONDS', 300),
        // Submissions faster than this after the challenge was issued are rejected as automated.
        'min_solve_seconds' => (int) env('CAPTCHA_MIN_SOLVE_SECONDS', 1),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
        'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ],

    // Hidden form field that humans leave empty; bots that fill every field are rejected.
    'honeypot_field' => 'website_url',

    'http_timeout_seconds' => 5,
];
