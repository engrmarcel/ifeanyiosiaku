<?php

namespace App\Domain\Announcements\Models;

use App\Domain\Announcements\AnnouncementObserver;
use App\Domain\Media\Models\MediaAsset;
use App\Policies\AnnouncementPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Homepage/site popup (REQ-ANN-1/2). Shown only while the global switch in Site settings is on,
 * the announcement is active and within its dates.
 *
 * @property string $ulid
 * @property string $title
 * @property string $message
 * @property int|null $media_id
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string $audience
 * @property string $placement
 * @property string $frequency
 * @property int $priority
 * @property string|null $campaign
 * @property bool $is_active
 */
#[ObservedBy(AnnouncementObserver::class)]
#[UsePolicy(AnnouncementPolicy::class)]
class Announcement extends Model
{
    use HasUlids;

    public const AUDIENCES = ['everyone' => 'Everyone', 'guests' => 'Visitors who are not signed in', 'members' => 'Signed-in members only'];

    public const PLACEMENTS = ['all' => 'Every page', 'home' => 'Homepage only'];

    public const FREQUENCIES = [
        'once' => 'Once per visitor (until they close it)',
        'session' => 'Once per visit',
        'daily' => 'At most once a day',
        'always' => 'On every page view',
    ];

    protected $guarded = ['id', 'ulid'];

    /** @var array<string, mixed> */
    protected $attributes = ['audience' => 'everyone', 'placement' => 'all', 'frequency' => 'once', 'priority' => 0, 'is_active' => false];

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
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }

    /** @return HasMany<AnnouncementEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AnnouncementEvent::class);
    }

    /**
     * Active and within its dates (ignores the global switch, which the caller checks).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** Draft, Scheduled, Live or Ended, for the admin list. */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'Off',
            $this->ends_at !== null && $this->ends_at->lte(now()) => 'Ended',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'Scheduled',
            default => 'Running',
        };
    }
}
