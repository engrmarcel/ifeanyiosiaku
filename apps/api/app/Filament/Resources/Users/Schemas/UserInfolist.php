<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('ulid')->label('Public ID')->copyable(),
                        TextEntry::make('timezone')->placeholder('Site default'),
                        TextEntry::make('roles.name')
                            ->label('Roles')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Role::tryFrom($state)?->label() ?? $state),
                        TextEntry::make('created_at')->label('Joined')->dateTime(),
                        TextEntry::make('last_login_at')->label('Last sign-in')->dateTime()->placeholder('Never'),
                    ]),
                Section::make('Security & status')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (UserStatus $state): string => ucfirst($state->value))
                            ->color(fn (UserStatus $state): string => $state === UserStatus::Active ? 'success' : 'danger'),
                        IconEntry::make('email_verified_at')->label('Email verified')->boolean()->getStateUsing(fn ($record) => $record->email_verified_at !== null),
                        IconEntry::make('two_factor_confirmed_at')->label('Two-factor authentication')->boolean()->getStateUsing(fn ($record) => $record->two_factor_confirmed_at !== null),
                        TextEntry::make('suspended_at')->dateTime()->placeholder('—'),
                        TextEntry::make('suspension_reason')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
