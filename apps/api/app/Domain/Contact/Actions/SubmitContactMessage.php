<?php

namespace App\Domain\Contact\Actions;

use App\Domain\Audit\PrivacyHash;
use App\Domain\Contact\Models\ContactMessage;
use App\Domain\Contact\Notifications\ContactMessageReceived;
use App\Domain\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Stores a contact-form message and emails it to the address set in Site settings. Messages that
 * look like link spam are kept (marked "spam") but not emailed. No automatic reply is sent to the
 * visitor, so the form cannot be used to send email to strangers.
 */
class SubmitContactMessage
{
    /** More links than this marks a message as probable spam. */
    private const MAX_LINKS = 3;

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, Request $request): ContactMessage
    {
        $clean = fn (mixed $v): string => trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v));

        $data = Validator::make([
            'name' => $clean($input['name'] ?? ''),
            'email' => $clean($input['email'] ?? ''),
            'subject' => $clean($input['subject'] ?? ''),
            'message' => $clean($input['message'] ?? ''),
            'page' => $clean($input['page'] ?? ''),
        ], [
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'string', 'max:254', 'email:rfc'],
            'subject' => ['nullable', 'string', 'max:150', 'not_regex:/[\r\n]/'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'page' => ['nullable', 'string', 'max:200', 'regex:#^/[A-Za-z0-9\-/]*$#'],
        ], [
            'message.min' => 'Please write a little more (at least 10 characters).',
            'name.not_regex' => 'Please enter your name on one line.',
            'subject.not_regex' => 'Please keep the subject on one line.',
        ])->validate();

        $spam = preg_match_all('#https?://|www\.#i', $data['message']) > self::MAX_LINKS;
        $userId = $request->user()?->getKey();

        $message = ContactMessage::query()->create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'subject' => ($data['subject'] ?? '') !== '' ? $data['subject'] : null,
            'message' => $data['message'],
            'status' => $spam ? 'spam' : 'new',
            'user_id' => is_int($userId) ? $userId : null,
            'ip_hash' => PrivacyHash::of($request->ip(), 'contact-ip'),
            'page' => ($data['page'] ?? '') !== '' ? $data['page'] : null,
        ]);

        $recipient = $this->settings->get('contact.recipient_email');

        if (! $spam && is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            // The message is safely stored; an email problem must not fail the visitor's submission.
            try {
                Notification::route('mail', $recipient)->notify(new ContactMessageReceived($message));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $message;
    }
}
