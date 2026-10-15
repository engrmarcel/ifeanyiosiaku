<?php

namespace App\Domain\Cms\Models;

use App\Policies\RedirectPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property int $hits
 * @property bool $is_automatic
 * @property int|null $created_by
 */
#[UsePolicy(RedirectPolicy::class)]
class Redirect extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_automatic' => 'boolean'];
    }
}
