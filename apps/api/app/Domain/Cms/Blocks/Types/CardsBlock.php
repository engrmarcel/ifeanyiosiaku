<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Blocks\Fields;
use App\Domain\Cms\Support\SafeUrl;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class CardsBlock extends Block
{
    public static function type(): string
    {
        return 'cards';
    }

    public static function label(): string
    {
        return 'Cards grid';
    }

    public static function icon(): string
    {
        return 'heroicon-o-rectangle-group';
    }

    public static function fields(): array
    {
        return [
            Select::make('columns')->options(['2' => '2 columns', '3' => '3 columns', '4' => '4 columns'])->default('3'),
            Repeater::make('items')
                ->schema([
                    TextInput::make('eyebrow')->maxLength(60),
                    TextInput::make('title')->required()->maxLength(120),
                    Textarea::make('body')->rows(3)->maxLength(600),
                    Fields::link('url'),
                    TextInput::make('link_label')->maxLength(40),
                    Fields::media('media'),
                ])
                ->columns(2)
                ->minItems(1)
                ->maxItems(24)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
        ];
    }

    public static function rules(): array
    {
        return [
            'columns' => ['nullable', 'in:2,3,4'],
            'items' => ['required', 'array', 'min:1', 'max:24'],
            'items.*.eyebrow' => ['nullable', 'string', 'max:60'],
            'items.*.title' => ['required', 'string', 'max:120'],
            'items.*.body' => ['nullable', 'string', 'max:600'],
            'items.*.url' => ['nullable', new SafeUrl],
            'items.*.link_label' => ['nullable', 'string', 'max:40'],
            'items.*.media' => ['nullable', 'string', 'max:26'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'columns' => (int) ($data['columns'] ?? 3),
            'items' => array_map(fn (array $i) => [
                'eyebrow' => $i['eyebrow'] ?? null,
                'title' => $i['title'] ?? '',
                'body' => $i['body'] ?? null,
                'url' => $i['url'] ?? null,
                'linkLabel' => $i['link_label'] ?? null,
                'media' => $context->media($i['media'] ?? null),
            ], self::items($data['items'] ?? null)),
        ];
    }

    public static function mediaIds(array $data): array
    {
        return array_values(array_filter(array_map(fn (array $i) => $i['media'] ?? null, self::items($data['items'] ?? null))));
    }
}
