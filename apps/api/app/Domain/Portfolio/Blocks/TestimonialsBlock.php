<?php

namespace App\Domain\Portfolio\Blocks;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Portfolio\Models\Testimonial;

class TestimonialsBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'testimonials';
    }

    public static function label(): string
    {
        return 'Testimonials';
    }

    public static function icon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public static function description(): ?string
    {
        return 'Testimonials with confirmed consent from Portfolio → Testimonials. Hidden when there are none.';
    }

    public static function fields(): array
    {
        return self::headerFields('What people say');
    }

    public static function rules(): array
    {
        return self::headerRules();
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            ...self::header($data),
            'items' => Testimonial::query()->publishable()->with('avatar.variants')->orderBy('sort_order')->get()
                ->map(fn (Testimonial $t) => [
                    'quote' => $t->quote,
                    'author' => $t->author_name,
                    'title' => $t->author_title,
                    'avatar' => $t->avatar?->toPublicArray(),
                ])->values()->all(),
        ];
    }
}
