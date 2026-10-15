<?php

namespace App\Domain\Portfolio\Blocks;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Portfolio\Profile;
use Filament\Forms\Components\Toggle;

class ProfileIntroBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'profile_intro';
    }

    public static function label(): string
    {
        return 'Profile introduction';
    }

    public static function icon(): string
    {
        return 'heroicon-o-user-circle';
    }

    public static function description(): ?string
    {
        return 'Portrait and biography from Portfolio → Profile.';
    }

    public static function fields(): array
    {
        return [
            ...self::headerFields('About me'),
            Toggle::make('show_portrait')->label('Show portrait')->default(true),
            Toggle::make('show_years')->label('Show years of experience')->default(true),
            Toggle::make('full_bio')->label('Show the full biography (otherwise the short one)')->default(false),
        ];
    }

    public static function rules(): array
    {
        return [
            ...self::headerRules(),
            'show_portrait' => ['nullable', 'boolean'],
            'show_years' => ['nullable', 'boolean'],
            'full_bio' => ['nullable', 'boolean'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        $profile = app(Profile::class)->toPublic();
        $full = (bool) ($data['full_bio'] ?? false) && $profile['bioHtml'] !== '';

        return [
            ...self::header($data),
            'name' => $profile['name'],
            'headline' => $profile['headline'],
            'shortBio' => $full ? null : $profile['shortBio'],
            'bioHtml' => $full ? $profile['bioHtml'] : '',
            'yearsExperience' => ($data['show_years'] ?? true) ? $profile['yearsExperience'] : null,
            'portrait' => ($data['show_portrait'] ?? true) ? $profile['portrait'] : null,
        ];
    }
}
