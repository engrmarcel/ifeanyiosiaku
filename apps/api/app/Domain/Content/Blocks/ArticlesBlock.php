<?php

namespace App\Domain\Content\Blocks;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Content\ArticlePresenter;
use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\Category;
use App\Domain\Portfolio\Blocks\SectionHeader;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

/** "Latest articles" section, optionally from one category. Hidden when there are none. */
class ArticlesBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'articles';
    }

    public static function label(): string
    {
        return 'Latest articles';
    }

    public static function icon(): string
    {
        return 'heroicon-o-newspaper';
    }

    public static function description(): ?string
    {
        return 'The newest published articles from Content → Articles. Hidden when there are none.';
    }

    public static function fields(): array
    {
        return [
            ...self::headerFields('Latest articles'),
            Select::make('category')
                ->label('Only from category')
                ->placeholder('All categories')
                ->options(fn (): array => Category::query()->where('type', Category::TYPE_ARTICLE)->orderBy('name')->pluck('name', 'slug')->all()),
            Select::make('limit')->options(['3' => '3', '6' => '6', '9' => '9'])->default('3'),
            Toggle::make('show_all_link')->label('Show an "All articles" link')->default(true),
        ];
    }

    public static function rules(): array
    {
        return [
            ...self::headerRules(),
            'category' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'in:3,6,9'],
            'show_all_link' => ['nullable', 'boolean'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        $category = filled($data['category'] ?? null) ? (string) $data['category'] : null;
        $presenter = app(ArticlePresenter::class);

        $articles = Article::query()->published()
            ->when($category !== null, fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('type', Category::TYPE_ARTICLE)->where('slug', $category)))
            ->with(['cover.variants', 'categories'])
            ->latest('published_at')
            ->limit((int) ($data['limit'] ?? 3))
            ->get();

        return [
            ...self::header($data),
            'items' => $articles->map(fn (Article $a) => $presenter->card($a))->values()->all(),
            'allUrl' => ($data['show_all_link'] ?? true)
                ? ($category !== null ? '/articles?category='.$category : '/articles')
                : null,
        ];
    }
}
