<?php

namespace App\Domain\Announcements;

use App\Domain\Announcements\Models\Announcement;
use App\Domain\Audit\AuditLogger;
use App\Domain\Media\MediaUsageRecorder;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/** Audit trail for announcements, and protects their image from deletion in the media library. */
class AnnouncementObserver
{
    public function __construct(
        private readonly MediaUsageRecorder $usages,
        private readonly AuditLogger $audit,
    ) {}

    public function saved(Announcement $announcement): void
    {
        $this->usages->sync($announcement, 'media_id', array_filter([(int) $announcement->media_id]));

        $changes = array_keys($announcement->getChanges());
        $this->audit->record($announcement->wasRecentlyCreated ? 'announcement.created' : 'announcement.updated', $announcement, [
            'title' => $announcement->title,
            'active' => $announcement->is_active,
            'changed' => array_values(array_diff($changes, ['updated_at'])),
        ], self::actor());
    }

    public function deleted(Announcement $announcement): void
    {
        $this->usages->forget($announcement);
        $this->audit->record('announcement.deleted', $announcement, ['title' => $announcement->title], self::actor());
    }

    private static function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
