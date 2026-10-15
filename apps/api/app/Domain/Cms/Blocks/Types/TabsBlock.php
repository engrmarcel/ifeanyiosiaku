<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Blocks\Fields;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class TabsBlock extends Block
{
    public static function type(): string
    {
        return 'tabs';
    }

    public static function label(): string
    {
        return 'Tabs';
    }

    public static function icon(): string
    {
        return 'heroicon-o-folder';
    }

    public static function fields(): array
    {
        return [
            TextInput::make('label')->label('Accessible name for the tab list')->maxLength(80)->placeholder('Technologies'),
            Repeater::make('items')
                ->schema([
                    TextInput::make('title')->label('Tab label')->required()->maxLength(40),
                    Fields::richText('body', 'Tab content')->required(),
                ])
                ->minItems(2)
                ->maxItems(10)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
        ];
    }

    public static function htmlFields(): array
    {
        return ['items.*.body'];
    }

    public static function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:2', 'max:10'],
            'items.*.title' => ['required', 'string', 'max:40'],
            'items.*.body' => ['required', 'string', 'max:20000'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'label' => $data['label'] ?? 'Tabs',
            'items' => array_map(fn (array $i) => [
                'title' => $i['title'] ?? '',
                'html' => $context->html($i['body'] ?? null),
            ], self::items($data['items'] ?? null)),
        ];
    }
}
