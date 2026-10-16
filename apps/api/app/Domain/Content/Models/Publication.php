<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\ContentObserver;
use App\Domain\Media\Models\MediaAsset;
use App\Policies\ContentPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An eBook, research paper or technical document, with a history of released versions.
 * Free publications only for now; paid ones are sold through the store (Milestone 3).
 *
 * @property string $ulid
 * @property string $slug
 * @property string $title
 * @property string $type
 * @property string|null $abstract
 * @property list<string>|null $authors
 * @property string|null $doi
 * @property string|null $isbn
 * @property int|null $cover_media_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property int $sort_order
 * @property array<string, mixed>|null $seo
 * @property Carbon|null $updated_at
 */
#[ObservedBy(ContentObserver::class)]
#[UsePolicy(ContentPolicy::class)]
class Publication extends Model
{
    use HasUlids, SoftDeletes;

    public const TYPES = [
        'ebook' => 'eBook',
        'research' => 'Research',
        'paper' => 'Paper',
        'technical' => 'Technical document',
    ];

    protected $guarded = ['id', 'ulid'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'type' => 'paper', 'sort_order' => 0];

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
        return ['published_at' => 'datetime', 'authors' => 'array', 'seo' => 'array'];
    }

    public function path(): string
    {
        return '/publications/'.$this->slug;
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    /** @return HasMany<PublicationVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PublicationVersion::class)->orderByDesc('released_at')->orderByDesc('id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
