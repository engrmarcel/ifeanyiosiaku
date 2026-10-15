<?php

namespace App\Domain\Cms\Blocks;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * A content block type (ADR-007). Each type declares:
 * - fields(): the admin form (Filament components) for its data;
 * - rules(): server-side validation, applied on every save;
 * - toPublic(): the props sent to the website (sanitised, media resolved);
 * - mediaIds(): media library files it references (for usage tracking and preloading).
 *
 * Stored shape (Filament Builder): {type: string, data: {...fields, visible: bool, anchor: ?string}}.
 */
abstract class Block
{
    /** Bump when the stored data shape changes, and handle older versions in toPublic(). */
    public const VERSION = 1;

    abstract public static function type(): string;

    abstract public static function label(): string;

    abstract public static function icon(): string;

    /** @return array<Component|Field> */
    abstract public static function fields(): array;

    /** @return array<string, mixed> */
    abstract public static function rules(): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    abstract public static function toPublic(array $data, BlockContext $context): array;

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function mediaIds(array $data): array
    {
        return [];
    }

    /**
     * Rich-text fields (dotted paths, "*" for list items) that are sanitised when content is saved.
     *
     * @return list<string>
     */
    public static function htmlFields(): array
    {
        return [];
    }

    /** Short description shown in the admin block picker. */
    public static function description(): ?string
    {
        return null;
    }

    /**
     * @param  array<int, mixed>|null  $items
     * @return list<array<string, mixed>>
     */
    protected static function items(?array $items): array
    {
        return array_values(array_filter($items ?? [], 'is_array'));
    }
}
