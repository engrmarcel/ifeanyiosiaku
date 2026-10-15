<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Blocks\Fields;
use Filament\Forms\Components\Select;

class RichTextBlock extends Block
{
    public static function type(): string
    {
        return 'rich_text';
    }

    public static function label(): string
    {
        return 'Text';
    }

    public static function icon(): string
    {
        return 'heroicon-o-document-text';
    }

    public static function description(): ?string
    {
        return 'Paragraphs, headings, lists, links, quotes and tables.';
    }

    public static function fields(): array
    {
        return [
            Fields::richText('body')->required(),
            Select::make('width')->options(['narrow' => 'Reading width', 'wide' => 'Wide'])->default('narrow'),
        ];
    }

    public static function htmlFields(): array
    {
        return ['body'];
    }

    public static function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:200000'],
            'width' => ['nullable', 'in:narrow,wide'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            'html' => $context->html($data['body'] ?? null),
            'width' => $data['width'] ?? 'narrow',
        ];
    }
}
