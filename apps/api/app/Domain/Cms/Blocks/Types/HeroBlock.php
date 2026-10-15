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
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;

class HeroBlock extends Block
{
    public static function type(): string
    {
        return 'hero';
    }

    public static function label(): string
    {
        return 'Hero';
    }

    public static function icon(): string
    {
        return 'heroicon-o-sparkles';
    }

    public static function description(): ?string
    {
        return 'Large introduction with headline, buttons, optional portrait and animated terminal.';
    }

    public static function fields(): array
    {
        return [
            TextInput::make('eyebrow')->maxLength(80)->placeholder('// software · ai · data'),
            TextInput::make('heading')->required()->maxLength(120),
            TextInput::make('subheading')->maxLength(200),
            Textarea::make('body')->rows(3)->maxLength(600),
            Grid::make(2)->schema([
                TextInput::make('primary_label')->label('Main button text')->maxLength(40),
                Fields::link('primary_url', 'Main button link'),
                TextInput::make('secondary_label')->label('Second button text')->maxLength(40),
                Fields::link('secondary_url', 'Second button link'),
            ]),
            Fields::media('media', 'Portrait or image'),
            Toggle::make('show_terminal')->label('Show animated terminal panel')->default(true)->live(),
            Repeater::make('terminal_lines')
                ->visible(fn ($get) => (bool) $get('show_terminal'))
                ->schema([
                    Select::make('kind')->options(['command' => 'Command ($ …)', 'output' => 'Output', 'highlight' => 'Highlighted output'])->default('command')->required(),
                    TextInput::make('text')->required()->maxLength(120),
                ])
                ->columns(2)
                ->maxItems(10)
                ->defaultItems(0),
        ];
    }

    public static function rules(): array
    {
        return [
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'heading' => ['required', 'string', 'max:120'],
            'subheading' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:600'],
            'primary_label' => ['nullable', 'string', 'max:40'],
            'primary_url' => ['nullable', new SafeUrl],
            'secondary_label' => ['nullable', 'string', 'max:40'],
            'secondary_url' => ['nullable', new SafeUrl],
            'media' => ['nullable', 'string', 'max:26'],
            'show_terminal' => ['nullable', 'boolean'],
            'terminal_lines' => ['nullable', 'array', 'max:10'],
            'terminal_lines.*.kind' => ['required', 'in:command,output,highlight'],
            'terminal_lines.*.text' => ['required', 'string', 'max:120'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        $cta = fn (string $p) => filled($data["{$p}_label"] ?? null) && filled($data["{$p}_url"] ?? null)
            ? ['label' => $data["{$p}_label"], 'url' => $data["{$p}_url"]]
            : null;

        return [
            'eyebrow' => $data['eyebrow'] ?? null,
            'heading' => $data['heading'] ?? '',
            'subheading' => $data['subheading'] ?? null,
            'body' => $data['body'] ?? null,
            'primary' => $cta('primary'),
            'secondary' => $cta('secondary'),
            'media' => $context->media($data['media'] ?? null),
            'terminal' => ($data['show_terminal'] ?? false)
                ? array_map(fn (array $line) => ['kind' => $line['kind'] ?? 'output', 'text' => (string) ($line['text'] ?? '')], self::items($data['terminal_lines'] ?? null))
                : null,
        ];
    }

    public static function mediaIds(array $data): array
    {
        return filled($data['media'] ?? null) ? [(string) $data['media']] : [];
    }
}
