<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Blocks\Fields;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class AccordionBlock extends Block
{
    public static function type(): string
    {
        return 'accordion';
    }

    public static function label(): string
    {
        return 'Accordion / FAQ';
    }

    public static function icon(): string
    {
        return 'heroicon-o-bars-3-bottom-left';
    }

    public static function fields(): array
    {
        return [
            Toggle::make('faq')->label('Mark up as frequently asked questions (helps search engines)')->default(false),
            Repeater::make('items')
                ->schema([
                    TextInput::make('title')->label('Question or title')->required()->maxLength(200),
                    Fields::richText('body', 'Answer')->required(),
                ])
                ->minItems(1)
                ->maxItems(40)
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
            'faq' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.title' => ['required', 'string', 'max:200'],
            'items.*.body' => ['required', 'string', 'max:20000'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'faq' => (bool) ($data['faq'] ?? false),
            'items' => array_map(fn (array $i) => [
                'title' => $i['title'] ?? '',
                'html' => $context->html($i['body'] ?? null),
            ], self::items($data['items'] ?? null)),
        ];
    }
}
