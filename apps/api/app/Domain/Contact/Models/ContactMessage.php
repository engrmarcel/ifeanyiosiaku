<?php

namespace App\Domain\Contact\Models;

use App\Models\User;
use App\Policies\ContactMessagePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message sent through the website's contact form. Kept for 24 months (DATABASE_DESIGN §5).
 *
 * @property string $ulid
 * @property string $name
 * @property string $email
 * @property string|null $subject
 * @property string $message
 * @property string $status
 * @property int|null $user_id
 * @property string|null $ip_hash
 * @property string|null $page
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 */
#[UsePolicy(ContactMessagePolicy::class)]
class ContactMessage extends Model
{
    use HasUlids, MassPrunable;

    public const STATUSES = ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'spam' => 'Spam', 'archived' => 'Archived'];

    protected $guarded = ['id', 'ulid'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'new'];

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
        return ['handled_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subMonths(24));
    }
}
