<?php

namespace App\Domain\Portfolio\Blocks;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Cms\Support\SafeUrl;
use App\Domain\Portfolio\Models\Certification;

class CertificationsBlock extends Block
{
    use SectionHeader;

    public static function type(): string
    {
        return 'certifications';
    }

    public static function label(): string
    {
        return 'Certifications';
    }

    public static function icon(): string
    {
        return 'heroicon-o-check-badge';
    }

    public static function description(): ?string
    {
        return 'Certifications from Portfolio → Certifications. Hidden when there are none.';
    }

    public static function fields(): array
    {
        return self::headerFields('Certifications');
    }

    public static function rules(): array
    {
        return self::headerRules();
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        return [
            ...self::header($data),
            'items' => Certification::query()->with('media.variants')->where('is_visible', true)
                ->orderBy('sort_order')->orderByDesc('issued_on')->get()
                ->map(fn (Certification $c) => [
                    'name' => $c->name,
                    'issuer' => $c->issuer,
                    'issued' => $c->issued_on?->format('M Y'),
                    'url' => $c->credential_url && SafeUrl::isSafe($c->credential_url) ? $c->credential_url : null,
                    'media' => $c->media?->toPublicArray(),
                ])->values()->all(),
        ];
    }
}
