<?php

namespace App\Domain\Newsletter;

use App\Domain\Newsletter\Models\NewsletterSubscriber;

/**
 * Signed links in newsletter emails. Confirmation links expire after 7 days and stop working once
 * a newer confirmation email is sent; unsubscribe links never expire (people must always be able to leave).
 */
class NewsletterLinks
{
    public const CONFIRM_DAYS = 7;

    public function confirmUrl(NewsletterSubscriber $subscriber): string
    {
        return $this->frontend('/newsletter/confirm', $subscriber->ulid, $this->confirmToken($subscriber));
    }

    public function unsubscribeUrl(NewsletterSubscriber $subscriber): string
    {
        return $this->frontend('/newsletter/unsubscribe', $subscriber->ulid, $this->unsubscribeToken($subscriber));
    }

    public function validConfirmToken(NewsletterSubscriber $subscriber, string $token): bool
    {
        $sentAt = $subscriber->confirmation_sent_at;

        return $sentAt !== null
            && $sentAt->gt(now()->subDays(self::CONFIRM_DAYS))
            && hash_equals($this->confirmToken($subscriber), $token);
    }

    public function validUnsubscribeToken(NewsletterSubscriber $subscriber, string $token): bool
    {
        return hash_equals($this->unsubscribeToken($subscriber), $token);
    }

    private function confirmToken(NewsletterSubscriber $subscriber): string
    {
        return $this->sign('confirm|'.$subscriber->ulid.'|'.$subscriber->confirmation_sent_at?->getTimestamp());
    }

    private function unsubscribeToken(NewsletterSubscriber $subscriber): string
    {
        return $this->sign('unsubscribe|'.$subscriber->ulid);
    }

    private function sign(string $value): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $value, 'newsletter|'.config('app.key'), true)), '+/', '-_'), '=');
    }

    private function frontend(string $path, string $id, string $token): string
    {
        return rtrim((string) config('platform.frontend_url'), '/').$path.'?'.http_build_query(['s' => $id, 't' => $token]);
    }
}
