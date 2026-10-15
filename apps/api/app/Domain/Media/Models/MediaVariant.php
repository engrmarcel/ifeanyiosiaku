<?php

namespace App\Domain\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $variant
 * @property string $filename
 * @property string $mime
 * @property int $width
 * @property int $height
 * @property int $size
 */
class MediaVariant extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<MediaAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
