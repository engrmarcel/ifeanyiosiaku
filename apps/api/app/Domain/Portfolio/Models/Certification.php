<?php

namespace App\Domain\Portfolio\Models;

use App\Domain\Media\Models\MediaAsset;
use App\Domain\Portfolio\PortfolioObserver;
use App\Policies\PortfolioPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property string|null $issuer
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property string|null $credential_id
 * @property string|null $credential_url
 * @property int|null $media_id
 * @property int $sort_order
 * @property bool $is_visible
 */
#[ObservedBy(PortfolioObserver::class)]
#[UsePolicy(PortfolioPolicy::class)]
class Certification extends Model
{
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_visible' => true, 'sort_order' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_on' => 'date', 'expires_on' => 'date', 'is_visible' => 'boolean'];
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }
}
