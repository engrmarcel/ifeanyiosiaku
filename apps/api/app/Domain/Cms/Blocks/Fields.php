<?php

namespace App\Domain\Cms\Blocks;

use App\Domain\Cms\Support\SafeUrl;
use App\Domain\Media\Models\MediaAsset;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/** Reusable admin form fields for blocks, so every block behaves the same way. */
class Fields
{
    /** Searchable picker over the media library; stores the asset's public ID. */
    public static function media(string $name, string $label = 'Image'): Select
    {
        return Select::make($name)
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => MediaAsset::query()
                ->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%")
                    ->orWhere('filename', 'like', "%{$search}%"))
                ->latest()
                ->limit(30)
                ->get()
                ->mapWithKeys(fn (MediaAsset $m) => [$m->ulid => self::mediaLabel($m)])
                ->all())
            ->getOptionLabelUsing(fn (?string $value): ?string => ($m = MediaAsset::query()->where('ulid', $value)->first()) ? self::mediaLabel($m) : null)
            ->options(fn (): array => MediaAsset::query()->latest()->limit(30)->get()
                ->mapWithKeys(fn (MediaAsset $m) => [$m->ulid => self::mediaLabel($m)])->all())
            ->helperText('Upload new images in Content → Media library.');
    }

    public static function link(string $name, string $label = 'Link'): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->maxLength(500)
            ->rule(new SafeUrl)
            ->placeholder('/about or https://…');
    }

    public static function richText(string $name, string $label = 'Text'): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->fileAttachments(false)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['h2', 'h3'],
                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                ['table'],
                ['undo', 'redo'],
            ]);
    }

    private static function mediaLabel(MediaAsset $media): string
    {
        $name = $media->title ?: ($media->alt_text ?: $media->filename);

        return "{$name} ({$media->width}×{$media->height})";
    }
}
