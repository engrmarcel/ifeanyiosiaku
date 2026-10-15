<?php

namespace App\Domain\Portfolio\Blocks;

use App\Domain\Cms\Support\RichTextSanitizer;
use App\Domain\Cms\Support\SafeUrl;
use App\Domain\Portfolio\Models\Project;

/** Public shapes for projects: a card for lists, and the full case study. */
class ProjectPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function card(Project $project): array
    {
        return [
            'slug' => $project->slug,
            'url' => '/projects/'.$project->slug,
            'title' => $project->title,
            'summary' => $project->summary,
            'role' => $project->role,
            'period' => $project->period,
            'technologies' => array_values(array_filter($project->technologies ?? [], 'is_string')),
            'cover' => $project->cover?->toPublicArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function full(Project $project): array
    {
        return [
            ...self::card($project),
            'client' => $project->client,
            'bodyHtml' => app(RichTextSanitizer::class)->clean($project->body),
            'links' => array_values(array_filter(
                array_map(fn ($l) => is_array($l) && SafeUrl::isSafe($l['url'] ?? null) && filled($l['url'] ?? null)
                    ? ['label' => (string) ($l['label'] ?? 'Link'), 'url' => (string) $l['url']]
                    : null, $project->links ?? []),
            )),
            'publishedAt' => $project->published_at?->toIso8601String(),
            'updatedAt' => $project->updated_at?->toIso8601String(),
        ];
    }
}
