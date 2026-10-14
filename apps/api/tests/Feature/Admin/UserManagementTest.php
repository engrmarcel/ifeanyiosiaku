<?php

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Actions\AssignRoles;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function staff(Role $role = Role::Admin): User
{
    return User::factory()->withTwoFactor()->role($role)->create()->fresh();
}

it('lists users for staff who may view them', function () {
    $admin = staff();
    $members = User::factory()->count(3)->create();

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)->assertCanSeeTableRecords($members);
});

it('hides user management from staff without permission', function () {
    $this->actingAs(staff(Role::Editor))->get(UserResource::getUrl('index'))->assertForbidden();
});

it('offers no way to create or delete accounts from the admin panel', function () {
    $admin = staff(Role::SuperAdmin);

    expect($admin->can('create', User::class))->toBeFalse()
        ->and($admin->can('delete', User::factory()->create()))->toBeFalse()
        ->and(array_keys(UserResource::getPages()))->toBe(['index', 'view']);
});

it('suspends a member from the admin panel and signs them out', function () {
    $admin = staff();
    $member = User::factory()->create();
    DB::table('sessions')->insert(['id' => str_repeat('s', 40), 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('suspend')->table($member), data: ['reason' => 'Spam links'])
        ->assertHasNoFormErrors();

    expect($member->fresh()->status)->toBe(UserStatus::Suspended)
        ->and(DB::table('sessions')->where('user_id', $member->id)->exists())->toBeFalse();
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.suspended', 'subject_id' => $member->id, 'actor_id' => $admin->id]);
});

it('requires a reason to suspend', function () {
    $this->actingAs(staff());
    $member = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('suspend')->table($member), data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    expect($member->fresh()->isActive())->toBeTrue();
});

it('reinstates a suspended member', function () {
    $this->actingAs(staff());
    $member = User::factory()->suspended()->create();

    Livewire::test(ViewUser::class, ['record' => $member->getRouteKey()])->callAction('reinstate');

    expect($member->fresh()->isActive())->toBeTrue();
});

it('lets a super administrator change roles and audits the change', function () {
    $owner = staff(Role::SuperAdmin);
    $member = User::factory()->role(Role::Member)->create();
    $this->actingAs($owner);

    Livewire::test(ViewUser::class, ['record' => $member->getRouteKey()])
        ->callAction('roles', data: ['roles' => [Role::Editor->value]]);

    expect($member->fresh()->hasRole(Role::Editor->value))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.roles_changed', 'subject_id' => $member->id]);
});

it('hides role changes from administrators without the roles permission', function () {
    $this->actingAs(staff(Role::Admin));
    $member = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $member->getRouteKey()])->assertActionHidden('roles');
});

it('only lets super administrators grant the super administrator role', function () {
    $admin = staff(Role::Admin);
    $member = User::factory()->create();

    app(AssignRoles::class)->handle($member, [Role::SuperAdmin->value], $admin);
})->throws(ValidationException::class);

it('never removes the last super administrator', function () {
    $owner = staff(Role::SuperAdmin);

    app(AssignRoles::class)->handle($owner, [Role::Admin->value], $owner);
})->throws(ValidationException::class, 'The last Super Administrator cannot be removed.');

it('signs out staff whose staff roles are removed', function () {
    $owner = staff(Role::SuperAdmin);
    $editor = staff(Role::Editor);
    DB::table('sessions')->insert(['id' => str_repeat('e', 40), 'user_id' => $editor->id, 'payload' => '', 'last_activity' => time()]);

    app(AssignRoles::class)->handle($editor, [Role::Member->value], $owner);

    expect(DB::table('sessions')->where('user_id', $editor->id)->exists())->toBeFalse();
});

it('ignores unknown role names', function () {
    $owner = staff(Role::SuperAdmin);
    $member = User::factory()->create();

    app(AssignRoles::class)->handle($member, ['member', 'god_mode'], $owner);

    expect($member->fresh()->getRoleNames()->all())->toBe(['member']);
});

it('shows the audit log read-only to permitted staff', function () {
    app(AuditLogger::class)->record('test.event');

    $this->actingAs(staff(Role::Admin))->get(AuditLogResource::getUrl('index'))->assertOk()->assertSee('test.event');
    $this->actingAs(staff(Role::Editor))->get(AuditLogResource::getUrl('index'))->assertForbidden();

    expect(array_keys(AuditLogResource::getPages()))->toBe(['index', 'view']);
});
