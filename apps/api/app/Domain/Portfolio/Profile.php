<?php

namespace App\Domain\Portfolio;

use App\Domain\Cms\Support\RichTextSanitizer;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Settings\SettingsRepository;

/**
 * The owner's public profile (name, headline, biography, portrait, years of experience), edited on
 * Admin → Portfolio → Profile and stored as settings. Never invent facts here: empty fields are
 * simply not shown.
 */
class Profile
{
    public const KEYS = [
        'profile.name',
        'profile.headline',
        'profile.short_bio',
        'profile.bio',
        'profile.years_experience',
        'profile.portrait',
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly RichTextSanitizer $sanitizer,
    ) {}

    /**
     * @return array{name: string, headline: string|null, shortBio: string|null, bioHtml: string, yearsExperience: int|null, portrait: array<string, mixed>|null}
     */
    public function toPublic(): array
    {
        $portraitId = $this->settings->get('profile.portrait');
        $portrait = is_string($portraitId) && $portraitId !== ''
            ? MediaAsset::query()->with('variants')->where('ulid', $portraitId)->first()
            : null;
        $years = $this->settings->get('profile.years_experience');

        return [
            'name' => (string) ($this->settings->get('profile.name') ?: config('platform.defaults')['site.name']),
            'headline' => $this->settings->get('profile.headline') ?: null,
            'shortBio' => $this->settings->get('profile.short_bio') ?: null,
            'bioHtml' => $this->sanitizer->clean($this->settings->get('profile.bio')),
            'yearsExperience' => is_numeric($years) && (int) $years > 0 ? (int) $years : null,
            'portrait' => $portrait?->toPublicArray(),
        ];
    }
}
