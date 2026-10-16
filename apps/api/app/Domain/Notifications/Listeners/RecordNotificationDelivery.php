<?php

namespace App\Domain\Notifications\Listeners;

use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
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
        // Accounts, or addresses emailed directly (contact alerts, newsletter confirmations).
        $address = match (true) {
            $notifiable instanceof User => $notifiable->email,
            $notifiable instanceof AnonymousNotifiable => is_string($notifiable->routes[$channel] ?? null) ? $notifiable->routes[$channel] : null,
            default => null,
        };

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
