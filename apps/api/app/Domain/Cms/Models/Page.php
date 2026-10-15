<?php

namespace App\Domain\Cms\Models;

use App\Domain\Cms\Enums\PageStatus;
use App\Policies\PagePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $ulid
 * @property string $title
 * @property string $slug
 * @property string $template
 * @property list<array{type: string, data: array<string, mixed>}>|null $blocks
 * @property array<string, mixed>|null $seo
 * @property bool $is_homepage
 * @property PageStatus $status
 * @property int|null $published_revision_id
 * @property Carbon|null $published_at
 * @property Carbon|null $publish_at
 * @property Carbon|null $unpublish_at
 */
#[UsePolicy(PagePolicy::class)]
class Page extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id', 'ulid'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'template' => 'default',
        'is_homepage' => false,
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'seo' => 'array',
            'is_homepage' => 'boolean',
            'status' => PageStatus::class,
            'published_at' => 'datetime',
            'publish_at' => 'datetime',
            'unpublish_at' => 'datetime',
        ];
    }

    /** @return HasMany<PageRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->orderByDesc('number');
    }

    /** @return BelongsTo<PageRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'published_revision_id');
    }

    public function isLive(): bool
    {
        return $this->status === PageStatus::Published && $this->published_revision_id !== null;
    }

    /** Public path of the page ("/" for the homepage). */
    public function path(): string
    {
        return $this->is_homepage ? '/' : '/'.$this->slug;
    }
}
