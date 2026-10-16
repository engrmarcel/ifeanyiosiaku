<?php

namespace App\Domain\Contact\Blocks;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Portfolio\Blocks\SectionHeader;
use Filament\Forms\Components\TextInput;

/** Contact form (name, email, subject, message) with CAPTCHA. Messages arrive in Admin → Inbox. */
class ContactFormBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'contact_form';
    }

    public static function label(): string
    {
        return 'Contact form';
    }

    public static function icon(): string
    {
        return 'heroicon-o-envelope';
    }

    public static function description(): ?string
    {
        return 'Visitors send you a message. Protected against spam; messages appear in Inbox → Contact messages.';
    }

    public static function fields(): array
    {
        return [
            ...self::headerFields('Get in touch'),
            TextInput::make('button_label')->label('Button text')->default('Send message')->maxLength(40),
        ];
    }

    public static function rules(): array
    {
        return [...self::headerRules(), 'button_label' => ['nullable', 'string', 'max:40']];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            ...self::header($data),
            'buttonLabel' => filled($data['button_label'] ?? null) ? (string) $data['button_label'] : 'Send message',
        ];
    }
}
