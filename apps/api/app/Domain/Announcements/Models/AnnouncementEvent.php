<?php

namespace App\Domain\Announcements\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One impression, click or dismissal. Visitors are identified only by a hashed random browser id
 * (or their account when signed in). Raw events are deleted after 13 months (DATABASE_DESIGN §5).
 *
 * @property int $announcement_id
 * @property int|null $user_id
 * @property string|null $visitor_hash
 * @property string $type
 */
class AnnouncementEvent extends Model
{
    use MassPrunable;

    public const TYPES = ['impression', 'click', 'dismiss'];

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subMonths(13));
    }
}
