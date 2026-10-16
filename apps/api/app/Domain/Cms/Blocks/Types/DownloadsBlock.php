<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Blocks\Fields;
use App\Domain\Portfolio\Blocks\SectionHeader;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

/** A list of PDF downloads from the media library (free, public files). */
class DownloadsBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'downloads';
    }

    public static function label(): string
    {
        return 'Downloads';
    }

    public static function icon(): string
    {
        return 'heroicon-o-arrow-down-tray';
    }

    public static function description(): ?string
    {
        return 'PDF files from the media library, with their size. Paid files are sold through the store instead.';
    }

    public static function fields(): array
    {
        return [
            ...self::headerFields('Downloads'),
            Repeater::make('files')
                ->schema([
                    Fields::document('media', 'PDF')->required(),
                    TextInput::make('label')->label('Name shown (optional)')->maxLength(120)->placeholder('Uses the file title'),
                    TextInput::make('description')->maxLength(300)->columnSpanFull(),
                ])
                ->columns(2)
                ->minItems(1)
                ->maxItems(20)
                ->collapsible()
                ->addActionLabel('Add file'),
        ];
    }

    public static function rules(): array
    {
        return [
            ...self::headerRules(),
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*.media' => ['required', 'string', 'max:26'],
            'files.*.label' => ['nullable', 'string', 'max:120'],
            'files.*.description' => ['nullable', 'string', 'max:300'],
        ];
    }

    public static function mediaIds(array $data): array
    {
        return array_values(array_filter(array_map(fn ($f) => is_array($f) ? ($f['media'] ?? null) : null, (array) ($data['files'] ?? [])), 'is_string'));
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        $files = [];

        foreach ((array) ($data['files'] ?? []) as $file) {
            $document = is_array($file) ? $context->document($file['media'] ?? null) : null;

            if ($document === null) {
                continue; // file deleted or not a PDF
            }

            $files[] = [
                'name' => filled($file['label'] ?? null) ? (string) $file['label'] : $document['name'],
                'description' => filled($file['description'] ?? null) ? (string) $file['description'] : null,
                'url' => $document['url'],
                'size' => $document['size'],
            ];
        }

        return [...self::header($data), 'files' => $files];
    }
}
