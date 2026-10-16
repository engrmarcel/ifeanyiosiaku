<?php

use App\Models\User;

it('tells the website that nobody is signed in without an error status', function () {
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/session')
        ->assertOk()
        ->assertExactJson(['data' => null])
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('returns the same account details as /me when signed in', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $session = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/session')->assertOk()->json('data');
    $me = $this->withHeaders($this->apiHeaders())->getJson('/api/v1/me')->assertOk()->json('data');

    expect($session)->toBe($me)->and($session['email'])->toBe($user->email);
});

it('still refuses /me to visitors', function () {
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/me')->assertUnauthorized();
});
