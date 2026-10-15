<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class TableBlock extends Block
{
    public static function type(): string
    {
        return 'table';
    }

    public static function label(): string
    {
        return 'Table';
    }

    public static function icon(): string
    {
        return 'heroicon-o-table-cells';
    }

    public static function fields(): array
    {
        return [
            TextInput::make('caption')->label('Table caption')->required()->maxLength(200),
            Textarea::make('rows')
                ->label('Rows')
                ->required()
                ->rows(8)
                ->maxLength(30000)
                ->helperText('One row per line; separate cells with a vertical bar, e.g.  Tool | Use | Level')
                ->extraInputAttributes(['style' => 'font-family: monospace']),
            Toggle::make('header')->label('First row is a header')->default(true),
        ];
    }

    public static function rules(): array
    {
        return [
            'caption' => ['required', 'string', 'max:200'],
            'rows' => ['required', 'string', 'max:30000'],
            'header' => ['nullable', 'boolean'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        $rows = array_values(array_filter(array_map(
            fn (string $line) => array_map('trim', explode('|', trim($line, " \t|"))),
            preg_split('/\r?\n/', (string) ($data['rows'] ?? '')) ?: [],
        ), fn (array $cells) => implode('', $cells) !== ''));

        $rows = array_slice($rows, 0, 200);
        $header = ($data['header'] ?? true) && $rows !== [] ? array_shift($rows) : null;

        return [
            'caption' => $data['caption'] ?? '',
            'header' => $header,
            'rows' => $rows,
        ];
    }
}
