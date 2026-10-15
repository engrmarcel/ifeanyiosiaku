<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class StatsBlock extends Block
{
    public static function type(): string
    {
        return 'stats';
    }

    public static function label(): string
    {
        return 'Statistics';
    }

    public static function icon(): string
    {
        return 'heroicon-o-chart-bar';
    }

    public static function description(): ?string
    {
        return 'Key figures such as "12+ years experience". Only publish figures you can stand behind.';
    }

    public static function fields(): array
    {
        return [
            Repeater::make('items')
                ->schema([
                    TextInput::make('value')->required()->maxLength(20)->placeholder('12+'),
                    TextInput::make('label')->required()->maxLength(60)->placeholder('Years of experience'),
                    TextInput::make('description')->maxLength(160),
                ])
                ->columns(3)
                ->minItems(1)
                ->maxItems(8),
        ];
    }

    public static function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:8'],
            'items.*.value' => ['required', 'string', 'max:20'],
            'items.*.label' => ['required', 'string', 'max:60'],
            'items.*.description' => ['nullable', 'string', 'max:160'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'items' => array_map(fn (array $i) => [
                'value' => $i['value'] ?? '',
                'label' => $i['label'] ?? '',
                'description' => $i['description'] ?? null,
            ], self::items($data['items'] ?? null)),
        ];
    }
}
