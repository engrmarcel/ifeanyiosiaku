<?php

namespace App\Domain\Portfolio\Blocks;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Portfolio\Models\Project;
use Filament\Forms\Components\Select;

class ProjectsBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'projects';
    }

    public static function label(): string
    {
        return 'Portfolio projects';
    }

    public static function icon(): string
    {
        return 'heroicon-o-briefcase';
    }

    public static function description(): ?string
    {
        return 'Published projects and case studies from Portfolio → Projects. Hidden when there are none.';
    }

    public static function fields(): array
    {
        return [
            ...self::headerFields('Selected work'),
            Select::make('selection')->options(['featured' => 'Featured projects only', 'all' => 'All published projects'])->default('featured'),
            Select::make('limit')->options(['3' => '3', '6' => '6', '9' => '9', '12' => '12'])->default('6'),
        ];
    }

    public static function rules(): array
    {
        return [
            ...self::headerRules(),
            'selection' => ['nullable', 'in:featured,all'],
            'limit' => ['nullable', 'in:3,6,9,12'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        $projects = Project::query()
            ->published()
            ->with('cover.variants')
            ->when(($data['selection'] ?? 'featured') === 'featured', fn ($q) => $q->where('is_featured', true))
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->limit((int) ($data['limit'] ?? 6))
            ->get();

        return [
            ...self::header($data),
            'items' => $projects->map(fn (Project $p) => ProjectPresenter::card($p))->values()->all(),
        ];
    }
}
