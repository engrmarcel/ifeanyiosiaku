<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;

/**
 * Syntax-highlighted code (REQ-CMS-6). Code is stored and shown as text; it is never executed.
 */
class CodeBlock extends Block
{
    public static function type(): string
    {
        return 'code';
    }

    public static function label(): string
    {
        return 'Code';
    }

    public static function icon(): string
    {
        return 'heroicon-o-code-bracket';
    }

    public static function fields(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('language')->options(config('cms.code_languages'))->default('plaintext')->searchable()->required(),
                TextInput::make('title')->label('File name or title')->maxLength(120)->placeholder('app/Services/Report.php'),
            ]),
            Textarea::make('code')->required()->rows(12)->maxLength(50000)->extraInputAttributes(['style' => 'font-family: monospace']),
            Grid::make(3)->schema([
                Toggle::make('line_numbers')->label('Line numbers')->default(true),
                Toggle::make('copy_button')->label('Copy button')->default(true),
                TextInput::make('highlight')->label('Highlight lines')->placeholder('3, 7-9')->maxLength(100)->regex('/^\s*\d+(\s*-\s*\d+)?(\s*,\s*\d+(\s*-\s*\d+)?)*\s*$/'),
            ]),
            Repeater::make('annotations')
                ->label('Line notes')
                ->schema([
                    TextInput::make('line')->numeric()->minValue(1)->required(),
                    TextInput::make('note')->required()->maxLength(300),
                ])
                ->columns(2)
                ->maxItems(30)
                ->defaultItems(0)
                ->collapsed(),
            TextInput::make('caption')->maxLength(300),
        ];
    }

    public static function rules(): array
    {
        return [
            'language' => ['required', 'in:'.implode(',', array_keys(config('cms.code_languages')))],
            'title' => ['nullable', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:50000'],
            'line_numbers' => ['nullable', 'boolean'],
            'copy_button' => ['nullable', 'boolean'],
            'highlight' => ['nullable', 'string', 'max:100', 'regex:/^\s*\d+(\s*-\s*\d+)?(\s*,\s*\d+(\s*-\s*\d+)?)*\s*$/'],
            'annotations' => ['nullable', 'array', 'max:30'],
            'annotations.*.line' => ['required', 'integer', 'min:1'],
            'annotations.*.note' => ['required', 'string', 'max:300'],
            'caption' => ['nullable', 'string', 'max:300'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'language' => $data['language'] ?? 'plaintext',
            'title' => $data['title'] ?? null,
            'code' => str_replace("\r\n", "\n", (string) ($data['code'] ?? '')),
            'lineNumbers' => (bool) ($data['line_numbers'] ?? true),
            'copyButton' => (bool) ($data['copy_button'] ?? true),
            'highlight' => self::parseLines($data['highlight'] ?? null),
            'annotations' => array_map(
                fn (array $a) => ['line' => (int) $a['line'], 'note' => (string) $a['note']],
                self::items($data['annotations'] ?? null),
            ),
            'caption' => $data['caption'] ?? null,
        ];
    }

    /**
     * "3, 7-9" → [3, 7, 8, 9] (capped to avoid huge ranges).
     *
     * @return list<int>
     */
    public static function parseLines(?string $spec): array
    {
        $lines = [];

        foreach (explode(',', (string) $spec) as $part) {
            if (preg_match('/^\s*(\d+)\s*(?:-\s*(\d+))?\s*$/', $part, $m)) {
                $from = (int) $m[1];
                $to = min(isset($m[2]) ? (int) $m[2] : $from, $from + 500);
                for ($i = $from; $i <= $to; $i++) {
                    $lines[$i] = $i;
                }
            }
        }

        return array_values($lines);
    }
}
