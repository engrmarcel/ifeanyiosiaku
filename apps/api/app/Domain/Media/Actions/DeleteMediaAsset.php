<?php

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Deletes a media asset and its files, refusing while any page or record still uses it. */
class DeleteMediaAsset
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(MediaAsset $asset, ?User $actor): void
    {
        $count = $asset->usages()->count();

        if ($count > 0) {
            throw ValidationException::withMessages([
                'media' => "This file is used in {$count} place(s). Remove it from those places before deleting it.",
            ]);
        }

        DB::transaction(function () use ($asset, $actor) {
            $this->audit->record('media.deleted', $asset, ['filename' => $asset->filename], $actor);
            $asset->variants()->delete();
            $asset->delete();
        });

        Storage::disk($asset->disk)->deleteDirectory($asset->directory);
    }
}
