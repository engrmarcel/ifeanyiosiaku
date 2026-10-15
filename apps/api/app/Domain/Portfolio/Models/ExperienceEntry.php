<?php

namespace App\Domain\Portfolio\Models;

use App\Domain\Portfolio\PortfolioObserver;
use App\Policies\PortfolioPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $title
 * @property string|null $organisation
 * @property string|null $location
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool $is_current
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_visible
 */
#[ObservedBy(PortfolioObserver::class)]
#[UsePolicy(PortfolioPolicy::class)]
class ExperienceEntry extends Model
{
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_visible' => true, 'is_current' => false, 'sort_order' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean', 'is_visible' => 'boolean'];
    }

    /** "Jan 2020 – Present" style label. */
    public function period(): ?string
    {
        if ($this->start_date === null) {
            return null;
        }

        $end = $this->is_current ? 'Present' : $this->end_date?->format('M Y');

        return $this->start_date->format('M Y').($end ? " – {$end}" : '');
    }
}
