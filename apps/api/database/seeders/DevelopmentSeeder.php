<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local/testing demo accounts. Never runs in production or staging.
 * Passwords are documented in docs/LOCAL_SETUP.md and only valid on a developer machine.
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevelopmentSeeder may only run in local or testing environments.');
        }

        $member = User::query()->firstOrCreate(
            ['email' => 'member@example.test'],
            ['name' => 'Demo Member', 'password' => 'local-dev-password'],
        );
        $member->forceFill(['email_verified_at' => now()])->save();
        $member->profile()->firstOrCreate([], ['display_name' => 'Demo Member']);
        $member->syncRoles([Role::Member->value]);
    }
}
