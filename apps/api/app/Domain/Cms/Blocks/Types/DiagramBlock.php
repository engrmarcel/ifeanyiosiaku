<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * A diagram written as text in Mermaid syntax (flowcharts, sequence diagrams, ER diagrams…).
 * The website draws it in the browser in Mermaid's strict mode (no scripts, no clickable
 * actions); the required description is what screen readers and search engines get.
 */
class DiagramBlock extends Block
{
    public static function type(): string
    {
        return 'diagram';
    }

    public static function label(): string
    {
        return 'Diagram';
    }

    public static function icon(): string
    {
        return 'heroicon-o-share';
    }

    public static function description(): ?string
    {
        return 'Flowcharts, sequence and data-model diagrams written as text (Mermaid).';
    }

    public static function fields(): array
    {
        return [
            Textarea::make('source')
                ->label('Diagram (Mermaid)')
                ->required()
                ->rows(10)
                ->maxLength(20000)
                ->extraInputAttributes(['style' => 'font-family: monospace'])
                ->placeholder("flowchart LR\n  Source[(Sales DB)] --> ETL[Python ETL] --> Model[Power BI model] --> Report")
                ->helperText('Mermaid syntax, see mermaid.js.org. Configuration lines starting with %%{ are not allowed.'),
            Textarea::make('description')
                ->label('Text description')
                ->required()
                ->rows(2)
                ->maxLength(1000)
                ->helperText('Describe what the diagram shows, for people who cannot see it.'),
            TextInput::make('caption')->maxLength(300),
            Toggle::make('show_source')->label('Let visitors view the diagram source')->default(false),
        ];
    }

    public static function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:20000', 'not_regex:/%%\s*\{/'],
            'description' => ['required', 'string', 'max:1000'],
            'caption' => ['nullable', 'string', 'max:300'],
            'show_source' => ['nullable', 'boolean'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'source' => (string) ($data['source'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'caption' => filled($data['caption'] ?? null) ? (string) $data['caption'] : null,
            'showSource' => (bool) ($data['show_source'] ?? false),
        ];
    }
}
