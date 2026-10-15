<?php

namespace App\Domain\Portfolio\Models;

use App\Domain\Media\Models\MediaAsset;
use App\Domain\Portfolio\PortfolioObserver;
use App\Policies\PortfolioPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Portfolio project / case study.
 *
 * @property string $ulid
 * @property string $slug
 * @property string $title
 * @property string|null $summary
 * @property string|null $body
 * @property string|null $role
 * @property string|null $client
 * @property string|null $period
 * @property list<string>|null $technologies
 * @property array<int, mixed>|null $links
 * @property int|null $cover_media_id
 * @property bool $is_featured
 * @property string $status
 * @property Carbon|null $published_at
 * @property int $sort_order
 */
#[ObservedBy(PortfolioObserver::class)]
#[UsePolicy(PortfolioPolicy::class)]
class Project extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id', 'ulid'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'is_featured' => false, 'sort_order' => 0];

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
            'technologies' => 'array',
            'links' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
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
