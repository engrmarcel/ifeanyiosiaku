<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates (or promotes) the owner's Super Administrator account. The password is typed
 * interactively and never passed on the command line, so it does not land in shell history.
 */
#[Signature('app:create-super-admin {--email= : Email address} {--name= : Full name}')]
#[Description('Create or promote a Super Administrator account')]
class CreateSuperAdmin extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $email = Str::lower($this->stringOption('email') ?? text('Email address', required: true));
        $existing = User::query()->where('email', $email)->first();

        $name = $existing->name ?? $this->stringOption('name') ?? text('Full name', default: 'Ifeanyi Osiaku', required: true);

        $data = ['email' => $email, 'name' => $name];
        $rules = ['email' => ['required', 'email:rfc', 'max:255'], 'name' => ['required', 'string', 'max:80']];

        if (! $existing) {
            $data['password'] = password('Password (min. 10 characters)', required: true);
            $data['password_confirmation'] = password('Confirm password', required: true);
            $rules['password'] = ['required', 'max:128', Password::min(10), 'confirmed'];
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($existing, $data) {
            $user = $existing ?? User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            $user->profile()->firstOrCreate([], ['display_name' => $user->name]);
            $user->assignRole(Role::SuperAdmin->value);

            return $user;
        });

        $audit->record('user.super_admin_granted', $user, ['via' => 'console']);

        $this->components->info(($existing ? 'Promoted' : 'Created')." Super Administrator: {$user->email}");
        $this->components->warn('Sign in on the website and set up two-factor authentication before opening /admin.');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
