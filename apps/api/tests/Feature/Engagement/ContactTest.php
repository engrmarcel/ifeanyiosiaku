<?php

use App\Domain\Contact\Models\ContactMessage;
use App\Domain\Contact\Notifications\ContactMessageReceived;
use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\SettingsRepository;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function sendContact(array $data = [], bool $withCaptcha = true)
{
    $token = $withCaptcha ? test()->solveCaptcha('contact') : null;

    return test()->withHeaders(test()->apiHeaders())->postJson('/api/v1/contact', [
        'name' => 'Ada Lovelace',
        'email' => 'Ada@Example.com',
        'subject' => 'Training enquiry',
        'message' => 'Could you run a Power BI workshop for our team?',
        'page' => '/contact',
        'captcha_token' => $token,
        ...$data,
    ]);
}

it('stores the message and emails it to the configured address', function () {
    Notification::fake();
    app(SettingsRepository::class)->set('contact.recipient_email', 'owner@example.com');

    sendContact()->assertCreated();

    $message = ContactMessage::query()->sole();
    expect($message->email)->toBe('ada@example.com')
        ->and($message->status)->toBe('new')
        ->and($message->ip_hash)->toHaveLength(64);

    Notification::assertSentTo(new AnonymousNotifiable, ContactMessageReceived::class, function ($notification, $channels, $notifiable) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'owner@example.com'
            && $mail->replyTo[0][0] === 'ada@example.com'
            && str_contains($mail->subject, 'Training enquiry');
    });
});

it('only stores the message when no recipient is set, and never replies to the sender', function () {
    Notification::fake();

    sendContact()->assertCreated();

    expect(ContactMessage::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('requires a valid CAPTCHA', function () {
    sendContact(withCaptcha: false)->assertUnprocessable()->assertJsonPath('error.code', 'captcha_failed');
    expect(ContactMessage::query()->count())->toBe(0);
});

it('validates input and rejects header injection', function () {
    // Every attempt counts towards the rate limit, so space them out.
    $this->travel(11)->minutes();
    sendContact(['email' => 'not-an-email'])->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['email']]]);
    $this->travel(11)->minutes();
    sendContact(['message' => 'short'])->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['message']]]);
    $this->travel(11)->minutes();
    sendContact(['name' => "Ada\r\nBcc: victim@example.com"])->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['name']]]);
    $this->travel(11)->minutes();
    sendContact(['subject' => "Hi\nBcc: x@example.com"])->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['subject']]]);
});

it('keeps link spam but does not email it', function () {
    Notification::fake();
    app(SettingsRepository::class)->set('contact.recipient_email', 'owner@example.com');

    sendContact(['message' => 'Buy now http://a.example http://b.example http://c.example www.d.example'])->assertCreated();

    expect(ContactMessage::query()->value('status'))->toBe('spam');
    Notification::assertNothingSent();
});

it('shows visitor text literally in the owner email', function () {
    $message = ContactMessage::query()->create(['name' => 'Eve', 'email' => 'eve@example.com', 'message' => '[Click here](https://evil.example) <script>alert(1)</script>']);

    $html = (string) (new ContactMessageReceived($message))->toMail(new AnonymousNotifiable)->render();

    expect($html)->not->toContain('<a href="https://evil.example"')
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('[Click here](https://evil.example)');
});

it('limits how often one visitor can send messages', function () {
    for ($i = 0; $i < 3; $i++) {
        sendContact()->assertCreated();
    }

    sendContact()->assertTooManyRequests();
});

it('lets staff read messages, which marks them as read', function () {
    $message = ContactMessage::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hello there, a question.']);
    $this->actingAs(contentStaff(Role::Admin));

    Livewire::test(ViewContactMessage::class, ['record' => $message->getKey()])->assertSee('Hello there, a question.');
    expect($message->fresh()->status)->toBe('read');

    $this->actingAs(contentStaff(Role::Editor))->get('/admin/contact-messages')->assertForbidden();
});
