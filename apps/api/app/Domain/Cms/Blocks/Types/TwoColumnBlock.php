<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Blocks\Fields;
use Filament\Forms\Components\Select;

class TwoColumnBlock extends Block
{
    public static function type(): string
    {
        return 'two_column';
    }

    public static function label(): string
    {
        return 'Two columns';
    }

    public static function icon(): string
    {
        return 'heroicon-o-view-columns';
    }

    public static function fields(): array
    {
        return [
            Select::make('ratio')->options(['1:1' => 'Equal', '2:1' => 'Wider left', '1:2' => 'Wider right'])->default('1:1'),
            Fields::richText('left', 'Left column'),
            Fields::media('left_media', 'Left image (shown instead of text when set)'),
            Fields::richText('right', 'Right column'),
            Fields::media('right_media', 'Right image (shown instead of text when set)'),
        ];
    }

    public static function htmlFields(): array
    {
        return ['left', 'right'];
    }

    public static function rules(): array
    {
        return [
            'ratio' => ['nullable', 'in:1:1,2:1,1:2'],
            'left' => ['nullable', 'string', 'max:50000'],
            'right' => ['nullable', 'string', 'max:50000'],
            'left_media' => ['nullable', 'string', 'max:26'],
            'right_media' => ['nullable', 'string', 'max:26'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'ratio' => $data['ratio'] ?? '1:1',
            'left' => ['html' => $context->html($data['left'] ?? null), 'media' => $context->media($data['left_media'] ?? null)],
            'right' => ['html' => $context->html($data['right'] ?? null), 'media' => $context->media($data['right_media'] ?? null)],
        ];
    }

    public static function mediaIds(array $data): array
    {
        return array_values(array_filter([$data['left_media'] ?? null, $data['right_media'] ?? null]));
    }
}
