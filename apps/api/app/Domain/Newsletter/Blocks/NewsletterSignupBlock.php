<?php

namespace App\Domain\Newsletter\Blocks;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Portfolio\Blocks\SectionHeader;
use App\Domain\Settings\SettingsRepository;
use Filament\Forms\Components\TextInput;

/** Newsletter sign-up (double opt-in). Hidden while sign-up is switched off in Site settings. */
class NewsletterSignupBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'newsletter_signup';
    }

    public static function label(): string
    {
        return 'Newsletter sign-up';
    }

    public static function icon(): string
    {
        return 'heroicon-o-paper-airplane';
    }

    public static function description(): ?string
    {
        return 'Email sign-up with confirmation link. Only visible while "Show the newsletter sign-up form" is on in Site settings.';
    }

    public static function fields(): array
    {
        return [
            ...self::headerFields('Stay in the loop'),
            TextInput::make('button_label')->label('Button text')->default('Subscribe')->maxLength(40),
        ];
    }

    public static function rules(): array
    {
        return [...self::headerRules(), 'button_label' => ['nullable', 'string', 'max:40']];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        $settings = app(SettingsRepository::class);

        return [
            ...self::header($data),
            'buttonLabel' => filled($data['button_label'] ?? null) ? (string) $data['button_label'] : 'Subscribe',
            'enabled' => $settings->bool('newsletter.enabled'),
            'consentText' => (string) $settings->get('newsletter.consent_text'),
        ];
    }
}
