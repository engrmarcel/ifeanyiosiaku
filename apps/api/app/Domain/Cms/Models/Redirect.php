<?php

namespace App\Domain\Cms\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property int $hits
 * @property bool $is_automatic
 */
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
