<?php

namespace App\Domain\Portfolio\Blocks;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Support\SafeUrl;
use App\Domain\Portfolio\Models\Service;

class ServicesBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'services';
    }

    public static function label(): string
    {
        return 'Services';
    }

    public static function icon(): string
    {
        return 'heroicon-o-wrench-screwdriver';
    }

    public static function description(): ?string
    {
        return 'Services from Portfolio → Services. Hidden when there are none.';
    }

    public static function fields(): array
    {
        return self::headerFields('Services');
    }

    public static function rules(): array
    {
        return self::headerRules();
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            ...self::header($data),
            'items' => Service::query()->where('is_visible', true)->orderBy('sort_order')->orderBy('title')->get()
                ->map(fn (Service $s) => [
                    'title' => $s->title,
                    'summary' => $s->summary,
                    'cta' => filled($s->cta_label) && filled($s->cta_url) && SafeUrl::isSafe($s->cta_url)
                        ? ['label' => $s->cta_label, 'url' => $s->cta_url]
                        : null,
                ])->values()->all(),
        ];
    }
}
