<?php

namespace App\Filament\Resources\Users;

use App\Domain\Identity\Actions\AssignRoles;
use App\Domain\Identity\Actions\ReinstateUser;
use App\Domain\Identity\Actions\SuspendUser;
use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Admin actions for user accounts. Each delegates to an Identity action so the same rules and
 * audit logging apply wherever the operation is triggered.
 */
class UserActions
{
    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label('Suspend')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (User $record): bool => $record->isActive() && (self::actor()?->can('suspend', $record) ?? false))
            ->requiresConfirmation()
            ->modalDescription('The member is signed out everywhere immediately and cannot sign in until reinstated.')
            ->schema([
                Textarea::make('reason')->label('Reason (recorded in the audit log)')->required()->maxLength(490),
            ])
            ->action(function (User $record, array $data): void {
                self::run(fn () => app(SuspendUser::class)->handle($record, (string) $data['reason'], self::requireActor()), 'Account suspended');
            });
    }

    public static function reinstate(): Action
    {
        return Action::make('reinstate')
            ->label('Reinstate')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->visible(fn (User $record): bool => ! $record->isActive() && (self::actor()?->can('suspend', $record) ?? false))
            ->requiresConfirmation()
            ->action(function (User $record): void {
                self::run(fn () => app(ReinstateUser::class)->handle($record, self::requireActor()), 'Account reinstated');
            });
    }

    public static function roles(): Action
    {
        return Action::make('roles')
            ->label('Change roles')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->visible(fn (User $record): bool => self::actor()?->can('assignRoles', $record) ?? false)
            ->fillForm(fn (User $record): array => ['roles' => $record->getRoleNames()->all()])
            ->schema([
                CheckboxList::make('roles')
                    ->options(collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => $role->label()])->all())
                    ->helperText('Staff roles require two-factor authentication to open the admin panel.')
                    ->columns(2),
            ])
            ->action(function (User $record, array $data): void {
                /** @var list<string> $roles */
                $roles = array_values($data['roles'] ?? []);
                self::run(fn () => app(AssignRoles::class)->handle($record, $roles, self::requireActor()), 'Roles updated');
            });
    }

    private static function run(callable $operation, string $success): void
    {
        try {
            $operation();
            Notification::make()->title($success)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Not allowed')->body(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    private static function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private static function requireActor(): User
    {
        return self::actor() ?? throw new \RuntimeException('No authenticated administrator.');
    }
}
