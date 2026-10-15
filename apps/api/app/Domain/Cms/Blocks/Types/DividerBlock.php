<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use Filament\Forms\Components\Select;

class DividerBlock extends Block
{
    public static function type(): string
    {
        return 'divider';
    }

    public static function label(): string
    {
        return 'Divider / spacing';
    }

    public static function icon(): string
    {
        return 'heroicon-o-minus';
    }

    public static function fields(): array
    {
        return [
            Select::make('style')->options(['line' => 'Line', 'space' => 'Space only'])->default('line'),
            Select::make('size')->options(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])->default('md'),
        ];
    }

    public static function rules(): array
    {
        return [
            'style' => ['nullable', 'in:line,space'],
            'size' => ['nullable', 'in:sm,md,lg'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return ['style' => $data['style'] ?? 'line', 'size' => $data['size'] ?? 'md'];
    }
}
