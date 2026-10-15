<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class TimelineBlock extends Block
{
    public static function type(): string
    {
        return 'timeline';
    }

    public static function label(): string
    {
        return 'Timeline';
    }

    public static function icon(): string
    {
        return 'heroicon-o-queue-list';
    }

    public static function fields(): array
    {
        return [
            Repeater::make('items')
                ->schema([
                    TextInput::make('when')->label('Date or period')->required()->maxLength(40)->placeholder('2019 – 2023'),
                    TextInput::make('title')->required()->maxLength(120),
                    TextInput::make('subtitle')->maxLength(120),
                    Textarea::make('body')->rows(2)->maxLength(800),
                ])
                ->columns(2)
                ->minItems(1)
                ->maxItems(40)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => trim(($state['when'] ?? '').' '.($state['title'] ?? '')) ?: null),
        ];
    }

    public static function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.when' => ['required', 'string', 'max:40'],
            'items.*.title' => ['required', 'string', 'max:120'],
            'items.*.subtitle' => ['nullable', 'string', 'max:120'],
            'items.*.body' => ['nullable', 'string', 'max:800'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'items' => array_map(fn (array $i) => [
                'when' => $i['when'] ?? '',
                'title' => $i['title'] ?? '',
                'subtitle' => $i['subtitle'] ?? null,
                'body' => $i['body'] ?? null,
            ], self::items($data['items'] ?? null)),
        ];
    }
}
