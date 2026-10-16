<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\ContentObserver;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One released version of a publication: a PDF in the media library and/or an external link
 * (e.g. the publisher's page).
 *
 * @property int $publication_id
 * @property string $version_label
 * @property string|null $changelog
 * @property int|null $file_media_id
 * @property string|null $external_url
 * @property Carbon $released_at
 */
#[ObservedBy(ContentObserver::class)]
class PublicationVersion extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['released_at' => 'date'];
    }

    /** @return BelongsTo<Publication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'file_media_id');
    }
}
