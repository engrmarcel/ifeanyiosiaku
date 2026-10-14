<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Actions\ReinstateUser;
use App\Domain\Identity\Actions\SuspendUser;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function storeSessionFor(User $user, string $id): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
}

it('suspends an account, revokes its sessions and audits the action', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    $member = User::factory()->create();
    storeSessionFor($member, str_repeat('a', 40));
    storeSessionFor($member, str_repeat('b', 40));

    app(SuspendUser::class)->handle($member, 'Spam', $admin);

    $member->refresh();
    expect($member->status)->toBe(UserStatus::Suspended)
        ->and($member->suspension_reason)->toBe('Spam')
        ->and(DB::table('sessions')->where('user_id', $member->id)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'user.suspended')->where('subject_id', $member->id)->value('actor_id'))->toBe($admin->id);
});

it('ends an already-open session of a suspended account on its next request', function () {
    $member = User::factory()->create();
    $this->actingAs($member)->withHeaders($this->apiHeaders())->getJson('/api/v1/me')->assertOk();

    $member->forceFill(['status' => UserStatus::Suspended])->save();

    $this->withHeaders($this->apiHeaders())->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'account_suspended');

    $this->assertGuest('web');
});

it('prevents staff from suspending themselves', function () {
    $admin = User::factory()->role(Role::Admin)->create();

    app(SuspendUser::class)->handle($admin, 'oops', $admin);
})->throws(ValidationException::class);

it('prevents ordinary administrators from suspending a super administrator', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    $owner = User::factory()->role(Role::SuperAdmin)->create();

    app(SuspendUser::class)->handle($owner, 'takeover', $admin);
})->throws(ValidationException::class);

it('reinstates a suspended account', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    $member = User::factory()->suspended()->create();

    app(ReinstateUser::class)->handle($member, $admin);

    expect($member->fresh()->isActive())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.reinstated')->exists())->toBeTrue();
});
