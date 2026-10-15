<?php

namespace App\Domain\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable page snapshot.
 *
 * @property int $page_id
 * @property int $number
 * @property string $kind draft|publish|restore
 * @property string $title
 * @property string $slug
 * @property list<array{type: string, data: array<string, mixed>}>|null $blocks
 * @property array<string, mixed>|null $seo
 * @property int $schema_version
 * @property string|null $note
 * @property Carbon $created_at
 */
class PageRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'seo' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Page revisions are immutable.'));
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
