<?php

namespace App\Domain\Newsletter\Models;

use App\Policies\NewsletterSubscriberPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Newsletter sign-up with recorded consent (what they agreed to, where, when). Only "subscribed"
 * people (who clicked the confirmation link) may receive newsletters.
 *
 * @property string $ulid
 * @property string $email
 * @property string $status
 * @property Carbon|null $consent_at
 * @property string|null $consent_source
 * @property string|null $consent_text
 * @property string|null $ip_hash
 * @property Carbon|null $confirmation_sent_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $unsubscribed_at
 */
#[UsePolicy(NewsletterSubscriberPolicy::class)]
class NewsletterSubscriber extends Model
{
    use HasUlids;

    public const PENDING = 'pending';

    public const SUBSCRIBED = 'subscribed';

    public const UNSUBSCRIBED = 'unsubscribed';

    protected $guarded = ['id', 'ulid'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => self::PENDING];

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
        return [
            'consent_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }
}
