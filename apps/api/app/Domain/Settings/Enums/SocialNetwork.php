<?php

namespace App\Domain\Settings\Enums;

/** Social profiles that can be linked from the footer and listed in search-engine data. */
enum SocialNetwork: string
{
    case LinkedIn = 'linkedin';
    case GitHub = 'github';
    case X = 'x';
    case YouTube = 'youtube';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case Medium = 'medium';
    case Email = 'email';
    case Website = 'website';

    public function label(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
            self::GitHub => 'GitHub',
            self::X => 'X (Twitter)',
            self::YouTube => 'YouTube',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::TikTok => 'TikTok',
            self::Medium => 'Medium',
            self::Email => 'Email',
            self::Website => 'Website',
        };
    }

    /**
     * The link to store, or null if it is not acceptable. Email accepts a plain address and
     * stores a mailto: link; every other network needs a full https:// address.
     */
    public function normaliseUrl(string $value): ?string
    {
        $value = trim($value);

        if ($this === self::Email) {
            $address = preg_replace('/^mailto:/i', '', $value) ?? '';

            return filter_var($address, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$address : null;
        }

        return str_starts_with(strtolower($value), 'https://') && filter_var($value, FILTER_VALIDATE_URL) && ! preg_match('/[\x00-\x20\x7F]/', $value)
            ? $value
            : null;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
