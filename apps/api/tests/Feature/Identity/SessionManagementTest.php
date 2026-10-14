<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Session listing reads the sessions table, as in production.
    config(['session.driver' => 'database']);
});

function signIn(User $user)
{
    $response = test()->withHeaders(test()->apiHeaders())
        ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk();

    return test()->keepSessionFrom($response);
}

it('lists the member\'s sessions without exposing raw session IDs', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => str_repeat('z', 40), 'user_id' => $user->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'Phone', 'payload' => '', 'last_activity' => time() - 60]);

    $response = signIn($user)->withHeaders($this->apiHeaders())->getJson('/api/v1/account/sessions')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and(collect($response->json('data'))->where('is_current', true))->toHaveCount(1)
        ->and($response->getContent())->not->toContain(str_repeat('z', 40));
});

it('never lists another member\'s sessions', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    DB::table('sessions')->insert(['id' => str_repeat('y', 40), 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()]);

    $response = signIn($user)->withHeaders($this->apiHeaders())->getJson('/api/v1/account/sessions')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('signs out other devices after confirming the password', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => str_repeat('x', 40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    signIn($user);

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/account/sessions/revoke-others', ['password' => 'wrong'])
        ->assertUnprocessable();

    $this->withHeaders($this->apiHeaders())
        ->postJson('/api/v1/account/sessions/revoke-others', ['password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.revoked', 1);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/me')->assertOk();
});

it('revokes a single other session but not someone else\'s', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => str_repeat('w', 40), 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
        ['id' => str_repeat('v', 40), 'user_id' => $other->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
    ]);

    signIn($user);
    $mine = collect($this->withHeaders($this->apiHeaders())->getJson('/api/v1/account/sessions')->json('data'))
        ->firstWhere('is_current', false)['id'];
    $theirs = substr(hash_hmac('sha256', str_repeat('v', 40), (string) config('app.key')), 0, 32);

    $this->withHeaders($this->apiHeaders())->deleteJson("/api/v1/account/sessions/{$theirs}")->assertNotFound();
    $this->withHeaders($this->apiHeaders())->deleteJson("/api/v1/account/sessions/{$mine}")->assertNoContent();

    expect(DB::table('sessions')->where('id', str_repeat('w', 40))->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', str_repeat('v', 40))->exists())->toBeTrue();
});

it('requires authentication', function () {
    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/account/sessions')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});
