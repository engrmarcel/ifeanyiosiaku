<?php

use App\Domain\Identity\Enums\Role;
use App\Domain\Newsletter\Models\NewsletterSubscriber;
use App\Domain\Newsletter\NewsletterLinks;
use App\Domain\Newsletter\Notifications\ConfirmNewsletterSubscription;
use App\Domain\Settings\SettingsRepository;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function subscribe(array $data = [])
{
    return test()->withHeaders(test()->apiHeaders())->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'Reader@Example.com',
        'consent' => true,
        'source' => 'block:home',
        'captcha_token' => test()->solveCaptcha('newsletter'),
        ...$data,
    ]);
}

/** @return array{s: string, t: string} */
function linkParams(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return ['s' => (string) $query['s'], 't' => (string) $query['t']];
}

beforeEach(function () {
    Notification::fake();
});

it('is switched off by default', function () {
    expect(app(SettingsRepository::class)->bool('newsletter.enabled'))->toBeFalse();
    subscribe()->assertNotFound();
});

it('subscribes only after the emailed confirmation link is used', function () {
    app(SettingsRepository::class)->set('newsletter.enabled', true);

    subscribe()->assertStatus(202);

    $subscriber = NewsletterSubscriber::query()->sole();
    expect($subscriber->email)->toBe('reader@example.com')
        ->and($subscriber->status)->toBe('pending')
        ->and($subscriber->consent_text)->toContain('unsubscribe')
        ->and($subscriber->consent_source)->toBe('block:home');

    $url = null;
    Notification::assertSentTo(new AnonymousNotifiable, ConfirmNewsletterSubscription::class, function ($n, $c, $notifiable) use (&$url) {
        $url = $n->toMail($notifiable)->actionUrl;

        return $notifiable->routes['mail'] === 'reader@example.com';
    });

    expect($url)->toStartWith(config('platform.frontend_url').'/newsletter/confirm?');

    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/newsletter/confirm', ['s' => linkParams($url)['s'], 't' => 'forged'])->assertStatus(410);
    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/newsletter/confirm', linkParams($url))->assertOk();

    expect($subscriber->fresh()->status)->toBe('subscribed')->and($subscriber->fresh()->confirmed_at)->not->toBeNull();
});

it('requires consent and answers the same way for existing subscribers', function () {
    app(SettingsRepository::class)->set('newsletter.enabled', true);

    subscribe(['consent' => false])->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['consent']]]);

    NewsletterSubscriber::query()->create(['email' => 'reader@example.com', 'status' => 'subscribed', 'confirmed_at' => now()]);
    $first = subscribe()->assertStatus(202)->json('message');

    expect($first)->toBe('Almost done — please check your inbox and click the link to confirm.');
    Notification::assertNothingSent(); // already subscribed: no email, nothing revealed
});

it('does not resend confirmations more than every ten minutes', function () {
    app(SettingsRepository::class)->set('newsletter.enabled', true);

    subscribe()->assertStatus(202);
    subscribe()->assertStatus(202);
    Notification::assertSentTimes(ConfirmNewsletterSubscription::class, 1);

    $this->travel(11)->minutes();
    subscribe()->assertStatus(202);
    Notification::assertSentTimes(ConfirmNewsletterSubscription::class, 2);
});

it('expires confirmation links after seven days and when a newer one is sent', function () {
    $subscriber = NewsletterSubscriber::query()->create(['email' => 'a@example.com', 'confirmation_sent_at' => now()]);
    $links = app(NewsletterLinks::class);
    $old = linkParams($links->confirmUrl($subscriber));

    $this->travel(8)->days();
    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/newsletter/confirm', $old)->assertStatus(410);

    $subscriber->forceFill(['confirmation_sent_at' => now()])->save();
    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/newsletter/confirm', $old)->assertStatus(410);
    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/newsletter/confirm', linkParams($links->confirmUrl($subscriber->fresh())))->assertOk();
});

it('always allows unsubscribing, even with sign-up switched off', function () {
    $subscriber = NewsletterSubscriber::query()->create(['email' => 'a@example.com', 'status' => 'subscribed', 'confirmed_at' => now()]);
    $params = linkParams(app(NewsletterLinks::class)->unsubscribeUrl($subscriber));

    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/newsletter/unsubscribe', [...$params, 't' => 'nope'])->assertStatus(410);
    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/newsletter/unsubscribe', $params)->assertOk();
    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/newsletter/unsubscribe', $params)->assertOk();

    expect($subscriber->fresh()->status)->toBe('unsubscribed');
});

it('exports only confirmed subscribers, neutralising spreadsheet formulas', function () {
    NewsletterSubscriber::query()->create(['email' => 'yes@example.com', 'status' => 'subscribed', 'confirmed_at' => now(), 'consent_source' => '=HYPERLINK("x")']);
    NewsletterSubscriber::query()->create(['email' => 'pending@example.com']);

    $this->actingAs(contentStaff(Role::Admin));
    ob_start();
    ListNewsletterSubscribers::export()->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)->toContain('yes@example.com')->not->toContain('pending@example.com')->toContain("'=HYPERLINK");
    $this->assertDatabaseHas('audit_logs', ['action' => 'newsletter.exported']);
    $this->actingAs(contentStaff(Role::Editor))->get('/admin/newsletter-subscribers')->assertForbidden();
});
