<?php

namespace App\Domain\Identity\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The signed link points at the API route on the public origin (APP_URL), which the website proxies.
 * After verification Fortify redirects to the website's account page.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your email address')
            ->greeting('Welcome!')
            ->line('Please confirm your email address to activate your account.')
            ->action('Confirm email address', $url)
            ->line('This link expires in '.config('auth.verification.expire', 60).' minutes.')
            ->line('If you did not create an account, you can ignore this email.');
    }
}
