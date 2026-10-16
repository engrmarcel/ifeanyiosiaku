<?php

namespace App\Domain\Newsletter;

use App\Domain\Audit\PrivacyHash;
use App\Domain\Newsletter\Models\NewsletterSubscriber;
use App\Domain\Newsletter\Notifications\ConfirmNewsletterSubscription;
use App\Domain\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Double opt-in newsletter list (SECURITY_THREAT_MODEL §consent). The response is the same whether
 * or not an address is already on the list, so the form cannot be used to discover subscribers.
 */
class Newsletter
{
    /** A new confirmation email for the same address is sent at most this often. */
    public const RESEND_MINUTES = 10;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly NewsletterLinks $links,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->bool('newsletter.enabled');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function subscribe(array $input, Request $request): void
    {
        $data = Validator::make($input, [
            'email' => ['required', 'string', 'max:254', 'email:rfc'],
            'consent' => ['accepted'],
            'source' => ['nullable', 'string', 'max:100', 'regex:#^[a-z0-9:/\-_]*$#i'],
        ], ['consent.accepted' => 'Please tick the box to agree to receive emails.'])->validate();

        $email = strtolower(trim((string) $data['email']));
        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email' => $email]);

        if ($subscriber->status === NewsletterSubscriber::SUBSCRIBED) {
            return; // already confirmed: nothing to do, and nothing revealed
        }

        if ($subscriber->exists && $subscriber->status === NewsletterSubscriber::PENDING
            && $subscriber->confirmation_sent_at?->gt(now()->subMinutes(self::RESEND_MINUTES))) {
            return; // a confirmation email was sent moments ago
        }

        $subscriber->fill([
            'status' => NewsletterSubscriber::PENDING,
            'consent_at' => now(),
            'consent_source' => $data['source'] ?? null,
            'consent_text' => mb_substr((string) $this->settings->get('newsletter.consent_text'), 0, 500),
            'ip_hash' => PrivacyHash::of($request->ip(), 'newsletter-ip'),
            'confirmation_sent_at' => now(),
            'unsubscribed_at' => null,
        ])->save();

        try {
            Notification::route('mail', $email)->notify(new ConfirmNewsletterSubscription($subscriber));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function confirm(string $id, string $token): bool
    {
        $subscriber = NewsletterSubscriber::query()->where('ulid', $id)->first();

        if ($subscriber === null) {
            return false;
        }

        if ($subscriber->status === NewsletterSubscriber::SUBSCRIBED) {
            return true; // clicking the link twice is fine
        }

        if ($subscriber->status !== NewsletterSubscriber::PENDING || ! $this->links->validConfirmToken($subscriber, $token)) {
            return false;
        }

        $subscriber->forceFill(['status' => NewsletterSubscriber::SUBSCRIBED, 'confirmed_at' => now()])->save();

        return true;
    }

    public function unsubscribe(string $id, string $token): bool
    {
        $subscriber = NewsletterSubscriber::query()->where('ulid', $id)->first();

        if ($subscriber === null || ! $this->links->validUnsubscribeToken($subscriber, $token)) {
            return false;
        }

        if ($subscriber->status !== NewsletterSubscriber::UNSUBSCRIBED) {
            $subscriber->forceFill(['status' => NewsletterSubscriber::UNSUBSCRIBED, 'unsubscribed_at' => now()])->save();
        }

        return true;
    }
}
