<?php

namespace App\Domain\Newsletter\Notifications;

use App\Domain\Newsletter\Models\NewsletterSubscriber;
use App\Domain\Newsletter\NewsletterLinks;
use App\Domain\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Double opt-in: nobody is subscribed until they click this link. */
class ConfirmNewsletterSubscription extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly NewsletterSubscriber $subscriber) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = (string) app(SettingsRepository::class)->get('site.name');

        return (new MailMessage)
            ->subject("Please confirm your subscription to {$site}")
            ->greeting('Almost done!')
            ->line("Someone (hopefully you) asked to receive occasional updates from {$site} at this address.")
            ->action('Yes, subscribe me', app(NewsletterLinks::class)->confirmUrl($this->subscriber))
            ->line('This link works for '.NewsletterLinks::CONFIRM_DAYS.' days. If you did not ask for this, ignore this email and you will not hear from us again.');
    }
}
