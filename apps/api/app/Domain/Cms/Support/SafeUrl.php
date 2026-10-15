<?php

namespace App\Domain\Cms\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Link targets editors may use: site-relative paths ("/about", "#contact") or http(s)/mailto URLs.
 * Blocks javascript:, data: and protocol-relative ("//evil.example") links.
 */
class SafeUrl implements ValidationRule
{
    public static function isSafe(?string $url): bool
    {
        if ($url === null || $url === '') {
            return true;
        }

        $url = trim($url);

        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '#')) {
            return true;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//') && ! str_starts_with($url, '/\\');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme === 'mailto') {
            return true;
        }

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isSafe($value)) {
            $fail('Use a page path such as /about, or a full https:// address.');
        }
    }
}
