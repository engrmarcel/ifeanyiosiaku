<?php

namespace App\Domain\Notifications\Listeners;

use App\Models\User;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Delivery log for outgoing notifications. Recipient addresses are stored only as hashes.
 */
class RecordNotificationDelivery
{
    public function sent(NotificationSent $event): void
    {
        $this->record($event->notifiable, $event->notification::class, $event->channel, 'sent');
    }

    public function failed(NotificationFailed $event): void
    {
        $error = $event->data['exception'] ?? null;

        $this->record(
            $event->notifiable,
            $event->notification::class,
            $event->channel,
            'failed',
            $error instanceof \Throwable ? Str::limit($error->getMessage(), 990) : null,
        );
    }

    private function record(mixed $notifiable, string $notification, string $channel, string $status, ?string $error = null): void
    {
        $address = $notifiable instanceof User ? $notifiable->email : null;

        DB::table('notification_deliveries')->insert([
            'user_id' => $notifiable instanceof User ? $notifiable->getKey() : null,
            'notification' => class_basename($notification),
            'channel' => $channel,
            'recipient_hash' => $address ? hash('sha256', Str::lower($address)) : null,
            'status' => $status,
            'error' => $error,
            'created_at' => now(),
        ]);
    }
}
