<?php

namespace App\Domain\Portfolio\Blocks;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Portfolio\Models\ExperienceEntry;

class ExperienceBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'experience';
    }

    public static function label(): string
    {
        return 'Experience timeline';
    }

    public static function icon(): string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function description(): ?string
    {
        return 'Roles from Portfolio → Experience. Hidden when there are none.';
    }

    public static function fields(): array
    {
        return self::headerFields('Experience');
    }

    public static function rules(): array
    {
        return self::headerRules();
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            ...self::header($data),
            'items' => ExperienceEntry::query()->where('is_visible', true)
                ->orderBy('sort_order')->orderByDesc('is_current')->orderByDesc('start_date')->get()
                ->map(fn (ExperienceEntry $e) => [
                    'title' => $e->title,
                    'organisation' => $e->organisation,
                    'location' => $e->location,
                    'period' => $e->period(),
                    'description' => $e->description,
                ])->values()->all(),
        ];
    }
}
