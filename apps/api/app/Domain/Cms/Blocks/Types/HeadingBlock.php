<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class HeadingBlock extends Block
{
    public static function type(): string
    {
        return 'heading';
    }

    public static function label(): string
    {
        return 'Section heading';
    }

    public static function icon(): string
    {
        return 'heroicon-o-h1';
    }

    public static function fields(): array
    {
        return [
            TextInput::make('eyebrow')->maxLength(80),
            TextInput::make('text')->label('Heading')->required()->maxLength(160),
            TextInput::make('intro')->maxLength(300),
            Select::make('level')->options(['2' => 'Section (H2)', '3' => 'Subsection (H3)'])->default('2'),
            Select::make('align')->options(['left' => 'Left', 'center' => 'Centre'])->default('left'),
        ];
    }

    public static function rules(): array
    {
        return [
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'text' => ['required', 'string', 'max:160'],
            'intro' => ['nullable', 'string', 'max:300'],
            'level' => ['nullable', 'in:2,3'],
            'align' => ['nullable', 'in:left,center'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'eyebrow' => $data['eyebrow'] ?? null,
            'text' => $data['text'] ?? '',
            'intro' => $data['intro'] ?? null,
            'level' => (int) ($data['level'] ?? 2),
            'align' => $data['align'] ?? 'left',
        ];
    }
}
