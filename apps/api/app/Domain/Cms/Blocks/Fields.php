<?php

namespace App\Domain\Cms\Blocks;

use App\Domain\Cms\Support\SafeUrl;
use App\Domain\Media\Models\MediaAsset;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;

/** Reusable admin form fields for blocks, so every block behaves the same way. */
class Fields
{
    /** Searchable picker over the media library's images; stores the asset's public ID. */
    public static function media(string $name, string $label = 'Image'): Select
    {
        return self::picker($name, $label, 'ulid', images: true)
            ->helperText('Upload new images in Content → Media library.');
    }

    /** Same picker, storing the asset's database ID (for models with a media foreign key). */
    public static function mediaById(string $name, string $label = 'Image'): Select
    {
        return self::picker($name, $label, 'id', images: true)
            ->helperText('Upload new images in Content → Media library.');
    }

    /** Picker over the media library's PDF documents, storing the asset's database ID. */
    public static function documentById(string $name, string $label = 'PDF document'): Select
    {
        return self::picker($name, $label, 'id', images: false)
            ->helperText('Upload PDFs in Content → Media library.');
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

    private static function picker(string $name, string $label, string $key, bool $images): Select
    {
        return Select::make($name)
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::options(self::library($images)
                ->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%")
                    ->orWhere('filename', 'like', "%{$search}%")), $key))
            ->getOptionLabelUsing(fn ($value): ?string => ($m = self::library($images)->where($key, $value)->first()) ? self::mediaLabel($m) : null)
            ->options(fn (): array => self::options(self::library($images), $key));
    }

    /** @return Builder<MediaAsset> */
    private static function library(bool $images): Builder
    {
        return MediaAsset::query()->where('mime', $images ? 'like' : '=', $images ? 'image/%' : 'application/pdf');
    }

    /**
     * @param  Builder<MediaAsset>  $query
     * @return array<int|string, string>
     */
    private static function options(Builder $query, string $key): array
    {
        $options = [];
        foreach ($query->latest()->limit(30)->get() as $media) {
            $options[$media->getAttribute($key)] = self::mediaLabel($media);
        }

        return $options;
    }

    private static function mediaLabel(MediaAsset $media): string
    {
        $name = $media->title ?: ($media->alt_text ?: $media->filename);

        return $media->isImage()
            ? "{$name} ({$media->width}×{$media->height})"
            : "{$name} (PDF, ".number_format($media->size / 1024 / 1024, 1).' MB)';
    }
}
