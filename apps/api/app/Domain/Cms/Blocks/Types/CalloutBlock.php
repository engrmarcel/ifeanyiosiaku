<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Blocks\Fields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class CalloutBlock extends Block
{
    public static function type(): string
    {
        return 'callout';
    }

    public static function label(): string
    {
        return 'Callout';
    }

    public static function icon(): string
    {
        return 'heroicon-o-information-circle';
    }

    public static function fields(): array
    {
        return [
            Select::make('tone')->options(['info' => 'Note', 'success' => 'Tip', 'warning' => 'Warning', 'danger' => 'Important'])->default('info'),
            TextInput::make('title')->maxLength(120),
            Fields::richText('body')->required(),
        ];
    }

    public static function htmlFields(): array
    {
        return ['body'];
    }

    public static function rules(): array
    {
        return [
            'tone' => ['nullable', 'in:info,success,warning,danger'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'tone' => $data['tone'] ?? 'info',
            'title' => $data['title'] ?? null,
            'html' => $context->html($data['body'] ?? null),
        ];
    }
}
