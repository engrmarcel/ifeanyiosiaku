<?php

namespace App\Domain\Portfolio\Blocks;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Portfolio\Models\Skill;
use Filament\Forms\Components\Toggle;

class ExpertiseBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'expertise';
    }

    public static function label(): string
    {
        return 'Expertise & technology stack';
    }

    public static function icon(): string
    {
        return 'heroicon-o-cpu-chip';
    }

    public static function description(): ?string
    {
        return 'Areas of expertise and technologies from Portfolio → Skills.';
    }

    public static function fields(): array
    {
        return [
            ...self::headerFields('Expertise'),
            Toggle::make('show_expertise')->label('Show areas of expertise')->default(true),
            Toggle::make('show_technologies')->label('Show technologies and tools')->default(true),
        ];
    }

    public static function rules(): array
    {
        return [
            ...self::headerRules(),
            'show_expertise' => ['nullable', 'boolean'],
            'show_technologies' => ['nullable', 'boolean'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        $skills = Skill::query()->where('is_visible', true)->orderBy('sort_order')->orderBy('name')->get();
        $map = fn (string $group) => $skills->where('group', $group)
            ->map(fn (Skill $s) => ['name' => $s->name, 'description' => $s->description])
            ->values()
            ->all();

        return [
            ...self::header($data),
            'expertise' => ($data['show_expertise'] ?? true) ? $map('expertise') : [],
            'technologies' => ($data['show_technologies'] ?? true) ? $map('technology') : [],
        ];
    }
}
