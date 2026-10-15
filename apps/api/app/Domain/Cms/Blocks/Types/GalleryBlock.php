<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Blocks\Fields;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class GalleryBlock extends Block
{
    public static function type(): string
    {
        return 'gallery';
    }

    public static function label(): string
    {
        return 'Gallery';
    }

    public static function icon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function fields(): array
    {
        return [
            Repeater::make('images')
                ->schema([
                    Fields::media('media')->required(),
                    TextInput::make('caption')->maxLength(200),
                ])
                ->columns(2)
                ->minItems(1)
                ->maxItems(24)
                ->reorderable()
                ->collapsible(),
            Select::make('columns')->options(['2' => '2 columns', '3' => '3 columns', '4' => '4 columns'])->default('3'),
        ];
    }

    public static function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:24'],
            'images.*.media' => ['required', 'string', 'max:26'],
            'images.*.caption' => ['nullable', 'string', 'max:200'],
            'columns' => ['nullable', 'in:2,3,4'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'columns' => (int) ($data['columns'] ?? 3),
            'images' => array_values(array_filter(array_map(
                fn (array $item) => ($media = $context->media($item['media'] ?? null))
                    ? ['media' => $media, 'caption' => $item['caption'] ?? null]
                    : null,
                self::items($data['images'] ?? null),
            ))),
        ];
    }

    public static function mediaIds(array $data): array
    {
        return array_values(array_filter(array_map(fn (array $i) => $i['media'] ?? null, self::items($data['images'] ?? null))));
    }
}
