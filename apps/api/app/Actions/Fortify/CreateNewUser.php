<?php

namespace App\Actions\Fortify;

use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\SettingsRepository;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        if (! $this->settings->bool('registration.enabled')) {
            throw ValidationException::withMessages(['email' => 'Registration is currently closed.']);
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => trim($input['name']),
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'timezone' => $input['timezone'] ?? null,
            ]);

            $user->profile()->create(['display_name' => trim($input['name'])]);
            $user->assignRole(Role::Member->value);

            return $user;
        });
    }
}
