<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\ProfileVisibility;
use App\Models\User;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ProfileVisibility $visibility
 * @property bool $show_on_leaderboard
 */
#[Fillable(['display_name', 'handle', 'bio', 'website', 'visibility', 'show_on_leaderboard'])]
#[UseFactory(ProfileFactory::class)]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => ProfileVisibility::class,
            'show_on_leaderboard' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
