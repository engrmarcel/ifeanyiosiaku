<?php

use App\Domain\Identity\Notifications\VerifyEmailNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrl(User $user): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);
}

it('verifies the email address from the signed link', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->withHeaders($this->apiHeaders())->getJson(verificationUrl($user))->assertNoContent();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('points verification links at the public website origin', function () {
    expect(verificationUrl(User::factory()->unverified()->create()))
        ->toStartWith(config('app.url').'/api/v1/auth/email/verify/');
});

it('rejects a tampered verification link', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->withHeaders($this->apiHeaders())
        ->getJson(verificationUrl($user).'tampered')
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a verification link for another account', function () {
    $user = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();

    $this->actingAs($other)->withHeaders($this->apiHeaders())->getJson(verificationUrl($user))->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired verification link', function () {
    $user = User::factory()->unverified()->create();
    $url = verificationUrl($user);
    $this->travel(2)->hours();

    $this->actingAs($user)->withHeaders($this->apiHeaders())->getJson($url)->assertForbidden();
});

it('resends the verification email on request', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertStatus(202);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('queues verification emails for reliable delivery', function () {
    expect(new VerifyEmailNotification)->toBeInstanceOf(ShouldQueue::class);
});
