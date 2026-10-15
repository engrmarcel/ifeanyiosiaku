<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class QuoteBlock extends Block
{
    public static function type(): string
    {
        return 'quote';
    }

    public static function label(): string
    {
        return 'Quotation';
    }

    public static function icon(): string
    {
        return 'heroicon-o-chat-bubble-bottom-center-text';
    }

    public static function fields(): array
    {
        return [
            Textarea::make('text')->label('Quotation')->required()->rows(3)->maxLength(1000),
            TextInput::make('attribution')->label('Who said it')->maxLength(120),
            TextInput::make('role')->label('Their role or source')->maxLength(160),
        ];
    }

    public static function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:1000'],
            'attribution' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:160'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'text' => $data['text'] ?? '',
            'attribution' => $data['attribution'] ?? null,
            'role' => $data['role'] ?? null,
        ];
    }
}
