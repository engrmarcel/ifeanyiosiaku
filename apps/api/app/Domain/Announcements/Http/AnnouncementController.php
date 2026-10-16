<?php

namespace App\Domain\Announcements\Http;

use App\Domain\Announcements\Models\Announcement;
use App\Domain\Announcements\Models\AnnouncementEvent;
use App\Domain\Audit\PrivacyHash;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Settings\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AnnouncementController
{
    /** An impression from the same browser is counted at most once in this many minutes. */
    private const IMPRESSION_WINDOW_MINUTES = 30;

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * The announcement to show on ?path=, or null. The global switch overrides everything else:
     * while it is off, nothing is returned even if an announcement is active and scheduled.
     */
    public function current(Request $request): JsonResponse
    {
        $path = '/'.trim((string) $request->query('path', '/'), '/');
        $announcement = $this->settings->bool('announcements.enabled') ? $this->select($path) : null;

        return response()->json(['data' => $announcement !== null ? self::present($announcement) : null])
            ->header('Cache-Control', 'private, no-store');
    }

    /** Records an impression, click or dismissal for the admin's statistics. */
    public function event(Request $request, string $ulid): Response
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', AnnouncementEvent::TYPES)],
            'visitor' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{16,64}$/'],
        ]);

        $announcement = $this->settings->bool('announcements.enabled')
            ? Announcement::query()->running()->where('ulid', $ulid)->first()
            : null;
        abort_if($announcement === null, 404);

        $userId = Auth::id();
        $visitor = PrivacyHash::of($data['visitor'] ?? null, 'announcement-visitor');

        if ($data['type'] === 'impression' && ($visitor !== null || $userId !== null)) {
            $recent = AnnouncementEvent::query()
                ->where('announcement_id', $announcement->getKey())
                ->where('type', 'impression')
                ->where('created_at', '>=', now()->subMinutes(self::IMPRESSION_WINDOW_MINUTES))
                ->where(fn ($q) => $userId !== null ? $q->where('user_id', $userId) : $q->where('visitor_hash', $visitor))
                ->exists();

            if ($recent) {
                return response()->noContent();
            }
        }

        AnnouncementEvent::query()->create([
            'announcement_id' => $announcement->getKey(),
            'user_id' => is_int($userId) ? $userId : null,
            'visitor_hash' => $visitor,
            'type' => $data['type'],
        ]);

        return response()->noContent();
    }

    private function select(string $path): ?Announcement
    {
        $signedIn = Auth::check();

        return Announcement::query()->running()
            ->whereIn('audience', ['everyone', $signedIn ? 'members' : 'guests'])
            ->when($path !== '/', fn ($q) => $q->where('placement', 'all'))
            ->with('media.variants')
            ->orderByDesc('priority')
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(Announcement $announcement): array
    {
        $media = $announcement->media;

        return [
            'id' => $announcement->ulid,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'image' => $media instanceof MediaAsset && $media->isImage() ? $media->toPublicArray() : null,
            'cta' => filled($announcement->cta_label) && filled($announcement->cta_url)
                ? ['label' => $announcement->cta_label, 'url' => $announcement->cta_url]
                : null,
            'frequency' => $announcement->frequency,
            // Changing the announcement's content shows it again to visitors who dismissed an earlier version.
            'version' => substr(sha1($announcement->title.'|'.$announcement->message.'|'.$announcement->updated_at?->getTimestamp()), 0, 12),
        ];
    }
}
