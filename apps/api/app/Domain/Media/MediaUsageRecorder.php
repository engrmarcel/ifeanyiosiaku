<?php

namespace App\Domain\Media;

use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use Illuminate\Database\Eloquent\Model;

/** Keeps the media_usages table in step with the media a record references. */
class MediaUsageRecorder
{
    /**
     * Replace the recorded usages for one field of a record.
     *
     * @param  iterable<int|string>  $mediaIds  internal IDs or public ULIDs
     */
    public function sync(Model $usable, string $field, iterable $mediaIds): void
    {
        $ids = collect($mediaIds)->filter()->unique()->values();

        $assetIds = MediaAsset::query()
            ->where(fn ($q) => $q->whereIn('id', $ids->filter(fn ($v) => is_int($v) || ctype_digit((string) $v)))
                ->orWhereIn('ulid', $ids->filter(fn ($v) => is_string($v) && ! ctype_digit($v))))
            ->pluck('id');

        $scope = MediaUsage::query()
            ->where('usable_type', $usable->getMorphClass())
            ->where('usable_id', $usable->getKey())
            ->where('field', $field);

        $scope->clone()->whereNotIn('media_asset_id', $assetIds)->delete();

        foreach ($assetIds as $assetId) {
            MediaUsage::query()->firstOrCreate([
                'media_asset_id' => $assetId,
                'usable_type' => $usable->getMorphClass(),
                'usable_id' => $usable->getKey(),
                'field' => $field,
            ]);
        }
    }

    /** Remove every usage recorded for a record (e.g. when it is permanently deleted). */
    public function forget(Model $usable): void
    {
        MediaUsage::query()
            ->where('usable_type', $usable->getMorphClass())
            ->where('usable_id', $usable->getKey())
            ->delete();
    }
}
