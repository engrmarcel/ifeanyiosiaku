<?php

namespace App\Domain\Contact\Notifications;

use App\Domain\Contact\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Tells the site owner about a new contact message. Replying to the email answers the sender. */
class ContactMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly ContactMessage $contactMessage) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = $this->contactMessage;
        // Names and subjects come from visitors: keep them to one short line in the email headers.
        $subject = Str::limit(trim((string) preg_replace('/\s+/', ' ', $m->subject ?: 'Website enquiry')), 80);

        return (new MailMessage)
            ->subject('New message: '.$subject)
            ->replyTo($m->email, Str::limit((string) preg_replace('/[\r\n]+/', ' ', $m->name), 80, ''))
            ->greeting('New contact message')
            ->line(self::literal("From: {$m->name} <{$m->email}>\nSubject: ".($m->subject ?: '—')))
            ->line(self::literal($m->message))
            ->action('Open in admin', rtrim((string) config('app.url'), '/').'/admin/contact-messages/'.$m->getKey())
            ->line('Reply to this email to answer the sender directly.');
    }

    /**
     * Visitor text shown exactly as typed: escaped, and kept out of the Markdown formatter so it
     * cannot turn into links or formatting in the owner's inbox.
     */
    private static function literal(string $text): HtmlString
    {
        return new HtmlString('<pre style="white-space:pre-wrap;font-family:inherit;margin:0">'.e($text).'</pre>');
    }
}
