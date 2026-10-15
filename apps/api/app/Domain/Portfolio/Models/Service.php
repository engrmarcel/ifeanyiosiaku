<?php

namespace App\Domain\Portfolio\Models;

use App\Domain\Portfolio\PortfolioObserver;
use App\Policies\PortfolioPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * A professional service offered (consulting, training, ...). Paid booking links arrive with the
 * appointments module.
 *
 * @property string $slug
 * @property string $title
 * @property string|null $summary
 * @property string|null $body
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property int $sort_order
 * @property bool $is_visible
 */
#[ObservedBy(PortfolioObserver::class)]
#[UsePolicy(PortfolioPolicy::class)]
class Service extends Model
{
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_visible' => true, 'sort_order' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }
}
