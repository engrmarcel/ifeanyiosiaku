<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cms\Blocks\BlockRegistry;
use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Support\SafeUrl;
use App\Domain\Media\MediaUsageRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Saves the working draft of a page (never visible to visitors) and records a draft revision.
 */
class SavePageDraft
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/';

    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly MediaUsageRecorder $usages,
        private readonly AuditLogger $audit,
        private readonly RecordPageRevision $revisions,
    ) {}

    /**
     * @param  array<string, mixed>  $input  title, slug, template, blocks, seo, publish_at, unpublish_at
     */
    public function handle(Page $page, array $input, ?User $actor): Page
    {
        $validated = Validator::make($input, [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required', 'string', 'max:150', 'regex:'.self::SLUG_PATTERN,
                Rule::notIn(config('cms.reserved_slugs')),
                Rule::unique('pages', 'slug')->ignore($page->getKey()),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $first = explode('/', (string) $value)[0];
                    if (in_array($first, config('cms.reserved_slugs'), true)) {
                        $fail("“{$first}” is reserved by the website. Choose another address.");
                    }
                },
            ],
            'template' => ['nullable', 'in:default,landing'],
            'seo' => ['nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:70'],
            'seo.description' => ['nullable', 'string', 'max:170'],
            'seo.canonical_url' => ['nullable', 'url:https,http', 'max:300'],
            'seo.noindex' => ['nullable', 'boolean'],
            'seo.og_media' => ['nullable', 'string', 'max:26'],
            'publish_at' => ['nullable', 'date'],
            'unpublish_at' => ['nullable', 'date', 'after:publish_at'],
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and hyphens, e.g. "about" or "services/training".',
        ])->validate();

        $blocks = $this->blocks->prepare(is_array($input['blocks'] ?? null) ? $input['blocks'] : []);

        if (isset($validated['seo']['canonical_url']) && ! SafeUrl::isSafe($validated['seo']['canonical_url'])) {
            unset($validated['seo']['canonical_url']);
        }

        return DB::transaction(function () use ($page, $validated, $blocks, $actor) {
            $page->fill([
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'template' => $validated['template'] ?? 'default',
                'seo' => $validated['seo'] ?? [],
                'blocks' => $blocks,
                'publish_at' => $validated['publish_at'] ?? null,
                'unpublish_at' => $validated['unpublish_at'] ?? null,
                'updated_by' => $actor?->getKey(),
            ]);

            if (! $page->exists) {
                $page->created_by = $actor?->getKey();
                $page->status = PageStatus::Draft;
            }

            if ($page->status === PageStatus::Draft && $page->publish_at !== null && $page->publish_at->isFuture()) {
                $page->status = PageStatus::Scheduled;
            } elseif ($page->status === PageStatus::Scheduled && $page->publish_at === null) {
                $page->status = PageStatus::Draft;
            }

            $page->save();

            $this->revisions->handle($page, 'draft', $actor);
            $this->usages->sync($page, 'draft', [
                ...$this->blocks->mediaIds($blocks),
                ...array_filter([$page->seo['og_media'] ?? null]),
            ]);
            $this->audit->record('page.draft_saved', $page, ['slug' => $page->slug], $actor);

            return $page;
        });
    }
}
