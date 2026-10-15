<?php

namespace App\Domain\Portfolio\Models;

use App\Domain\Media\Models\MediaAsset;
use App\Domain\Portfolio\PortfolioObserver;
use App\Policies\PortfolioPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A real testimonial. It is only shown publicly once the author's consent is confirmed.
 *
 * @property string $author_name
 * @property string|null $author_title
 * @property string $quote
 * @property int|null $avatar_media_id
 * @property Carbon|null $consent_confirmed_at
 * @property int $sort_order
 * @property bool $is_visible
 */
#[ObservedBy(PortfolioObserver::class)]
#[UsePolicy(PortfolioPolicy::class)]
class Testimonial extends Model
{
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_visible' => true, 'sort_order' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['consent_confirmed_at' => 'datetime', 'is_visible' => 'boolean'];
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function avatar(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'avatar_media_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublishable(Builder $query): Builder
    {
        return $query->where('is_visible', true)->whereNotNull('consent_confirmed_at');
    }
}
