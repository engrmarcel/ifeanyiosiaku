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

$requireStaffTwoFactor = env('REQUIRE_STAFF_TWO_FACTOR');

return [

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3100'), '/'),

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Africa/Lagos'),

    // Shared secret and internal address used to tell the website to refresh cached pages.
    'web_revalidate_secret' => env('WEB_REVALIDATE_SECRET'),
    'web_internal_url' => env('WEB_INTERNAL_URL', 'http://127.0.0.1:3100'),

    'defaults' => [
        'site.name' => 'Ifeanyi Osiaku',
        'site.tagline' => 'Software Developer | AI Enthusiast | Business Intelligence & Data Analytics Professional',
        'site.footer_note' => 'Software · AI · Business Intelligence · Data',

        // Header and footer links, social profiles and accent colour (Admin → Content → Navigation & footer).
        // Social profiles start empty: only real accounts are added, by the owner.
        'navigation.header' => [
            ['label' => 'About', 'url' => '/#about', 'new_tab' => false, 'addon' => null],
            ['label' => 'Work', 'url' => '/#work', 'new_tab' => false, 'addon' => null],
            ['label' => 'Services', 'url' => '/#services', 'new_tab' => false, 'addon' => null],
            ['label' => 'Contact', 'url' => '/contact', 'new_tab' => false, 'addon' => null],
        ],
        'navigation.footer' => [],
        'social.links' => [],
        'theme.accent' => 'green',

        // Search engines may index the public website. Turn off to hide it (e.g. on a staging copy).
        'seo.allow_indexing' => true,

        // The announcement popup is OFF until an administrator switches it on (REQ-ANN-1).
        'announcements.enabled' => false,

        // Staff two-factor requirement (ADR-026): OFF during development, ON in production.
        // REQUIRE_STAFF_TWO_FACTOR overrides the environment-based default when set.
        'security.require_staff_two_factor' => $requireStaffTwoFactor === null || $requireStaffTwoFactor === ''
            ? env('APP_ENV') === 'production'
            : filter_var($requireStaffTwoFactor, FILTER_VALIDATE_BOOL),

        'captcha.driver' => env('CAPTCHA_DRIVER', 'first_party'),

        'registration.enabled' => true,

        // Contact form: messages are always kept in Admin → Inbox; they are also emailed here when set.
        'contact.recipient_email' => null,

        // Newsletter sign-up is OFF until an email provider can deliver confirmation emails.
        'newsletter.enabled' => false,
        'newsletter.consent_text' => 'Send me occasional emails about new articles, courses and events. I can unsubscribe at any time.',

        // OFF by default: the website discourages saving images, right-click and developer-tools shortcuts.
        // This deters casual copying only; it cannot technically prevent it (see DECISIONS ADR-024).
        'protection.allow_saving_and_inspect' => false,

        // Public profile (approved draft copy, PRODUCT_REQUIREMENTS §3). Editable in Admin → Profile.
        'profile.name' => 'Ifeanyi Osiaku',
        'profile.headline' => 'Software Developer | AI Enthusiast | Business Intelligence & Data Analytics Professional',
        'profile.short_bio' => 'Ifeanyi Osiaku is a technology professional focused on software development, artificial intelligence, business intelligence, data analytics, and data visualization. His work and educational content emphasize turning technical knowledge and complex data into practical solutions to real-world problems.',
        'profile.bio' => null,
        'profile.years_experience' => 12,
        'profile.portrait' => null,

        // Add-on modules (ADR-025). All ON by default; administrators can switch each one off.
        'addons.commerce.enabled' => true,
        'addons.learning.enabled' => true,
        'addons.community.enabled' => true,
        'addons.rewards.enabled' => true,
    ],

    // Settings that may be exposed to the public website (never secrets).
    'public_settings' => [
        'site.name',
        'site.tagline',
        'announcements.enabled',
        'captcha.driver',
        'registration.enabled',
        'newsletter.enabled',
        'newsletter.consent_text',
        'protection.allow_saving_and_inspect',
        'addons.commerce.enabled',
        'addons.learning.enabled',
        'addons.community.enabled',
        'addons.rewards.enabled',
    ],

];
