<?php

namespace App\Domain\Portfolio\Models;

use App\Domain\Portfolio\PortfolioObserver;
use App\Policies\PortfolioPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * An area of expertise ("expertise") or a technology/tool ("technology").
 *
 * @property string $name
 * @property string $group
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_visible
 */
#[ObservedBy(PortfolioObserver::class)]
#[UsePolicy(PortfolioPolicy::class)]
class Skill extends Model
{
    public const GROUPS = ['expertise' => 'Area of expertise', 'technology' => 'Technology / tool'];

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
