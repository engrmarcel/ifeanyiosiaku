<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\ContentObserver;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use App\Policies\ArticlePolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A technical article written in Markdown. Visible once published and its publish time has passed
 * (a future time schedules it).
 *
 * @property string $ulid
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property int|null $cover_media_id
 * @property int|null $author_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property int $reading_minutes
 * @property array<string, mixed>|null $seo
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 */
#[ObservedBy(ContentObserver::class)]
#[UsePolicy(ArticlePolicy::class)]
class Article extends Model
{
    use HasUlids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $guarded = ['id', 'ulid'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => self::STATUS_DRAFT, 'reading_minutes' => 1, 'body' => ''];

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
        return ['published_at' => 'datetime', 'seo' => 'array'];
    }

    public function path(): string
    {
        return '/articles/'.$this->slug;
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_at !== null && $this->published_at->lte(now());
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return MorphToMany<Category, $this> */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')->orderBy('sort_order')->orderBy('name');
    }

    /** @return MorphToMany<Tag, $this> */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->orderBy('name');
    }

    /** @return MorphMany<ContentRevision, $this> */
    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisionable')->orderByDesc('number');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
