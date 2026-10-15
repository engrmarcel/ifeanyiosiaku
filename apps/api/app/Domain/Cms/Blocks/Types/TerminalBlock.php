<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class TerminalBlock extends Block
{
    public static function type(): string
    {
        return 'terminal';
    }

    public static function label(): string
    {
        return 'Terminal panel';
    }

    public static function icon(): string
    {
        return 'heroicon-o-command-line';
    }

    public static function fields(): array
    {
        return [
            TextInput::make('title')->default('~/project')->maxLength(60),
            Repeater::make('lines')
                ->schema([
                    Select::make('kind')->options(['command' => 'Command ($ …)', 'output' => 'Output', 'highlight' => 'Highlighted output', 'comment' => 'Comment'])->default('command')->required(),
                    TextInput::make('text')->required()->maxLength(200),
                ])
                ->columns(2)
                ->minItems(1)
                ->maxItems(40),
            Toggle::make('animate')->label('Type lines in one by one')->default(true),
        ];
    }

    public static function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:60'],
            'lines' => ['required', 'array', 'min:1', 'max:40'],
            'lines.*.kind' => ['required', 'in:command,output,highlight,comment'],
            'lines.*.text' => ['required', 'string', 'max:200'],
            'animate' => ['nullable', 'boolean'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'title' => $data['title'] ?? '~',
            'animate' => (bool) ($data['animate'] ?? true),
            'lines' => array_map(fn (array $l) => ['kind' => $l['kind'] ?? 'output', 'text' => (string) ($l['text'] ?? '')], self::items($data['lines'] ?? null)),
        ];
    }
}
