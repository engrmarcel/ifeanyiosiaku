<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Blocks\Fields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class ImageBlock extends Block
{
    public static function type(): string
    {
        return 'image';
    }

    public static function label(): string
    {
        return 'Image';
    }

    public static function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public static function fields(): array
    {
        return [
            Fields::media('media')->required(),
            TextInput::make('caption')->maxLength(300),
            Select::make('size')->options(['normal' => 'Reading width', 'wide' => 'Wide', 'full' => 'Full width'])->default('normal'),
        ];
    }

    public static function rules(): array
    {
        return [
            'media' => ['required', 'string', 'max:26'],
            'caption' => ['nullable', 'string', 'max:300'],
            'size' => ['nullable', 'in:normal,wide,full'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'media' => $context->media($data['media'] ?? null),
            'caption' => $data['caption'] ?? null,
            'size' => $data['size'] ?? 'normal',
        ];
    }

    public static function mediaIds(array $data): array
    {
        return filled($data['media'] ?? null) ? [(string) $data['media']] : [];
    }
}
