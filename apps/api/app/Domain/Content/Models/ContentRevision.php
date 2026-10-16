<?php

namespace App\Domain\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Immutable snapshot of an article (or other non-page content) taken on every save.
 *
 * @property int $number
 * @property array<string, mixed> $payload
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 */
class ContentRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    /** @return MorphTo<Model, $this> */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
