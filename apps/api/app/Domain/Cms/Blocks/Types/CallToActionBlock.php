<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Blocks\Fields;
use App\Domain\Cms\Support\SafeUrl;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

class CallToActionBlock extends Block
{
    public static function type(): string
    {
        return 'cta';
    }

    public static function label(): string
    {
        return 'Call to action';
    }

    public static function icon(): string
    {
        return 'heroicon-o-cursor-arrow-rays';
    }

    public static function fields(): array
    {
        return [
            TextInput::make('eyebrow')->maxLength(80),
            TextInput::make('heading')->required()->maxLength(140),
            Textarea::make('body')->rows(2)->maxLength(400),
            Grid::make(2)->schema([
                TextInput::make('primary_label')->label('Button text')->required()->maxLength(40),
                Fields::link('primary_url', 'Button link')->required(),
                TextInput::make('secondary_label')->label('Second button text')->maxLength(40),
                Fields::link('secondary_url', 'Second button link'),
            ]),
        ];
    }

    public static function rules(): array
    {
        return [
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'heading' => ['required', 'string', 'max:140'],
            'body' => ['nullable', 'string', 'max:400'],
            'primary_label' => ['required', 'string', 'max:40'],
            'primary_url' => ['required', new SafeUrl],
            'secondary_label' => ['nullable', 'string', 'max:40'],
            'secondary_url' => ['nullable', new SafeUrl],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'eyebrow' => $data['eyebrow'] ?? null,
            'heading' => $data['heading'] ?? '',
            'body' => $data['body'] ?? null,
            'primary' => ['label' => $data['primary_label'] ?? '', 'url' => $data['primary_url'] ?? '/'],
            'secondary' => filled($data['secondary_label'] ?? null) && filled($data['secondary_url'] ?? null)
                ? ['label' => $data['secondary_label'], 'url' => $data['secondary_url']]
                : null,
        ];
    }
}
