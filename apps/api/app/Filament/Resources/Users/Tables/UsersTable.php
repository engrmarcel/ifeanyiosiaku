<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use App\Filament\Resources\Users\UserActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Role::tryFrom($state)?->label() ?? $state),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => ucfirst($state->value))
                    ->color(fn (UserStatus $state): string => $state === UserStatus::Active ? 'success' : 'danger'),
                IconColumn::make('email_verified_at')->label('Verified')->boolean()->getStateUsing(fn ($record) => $record->email_verified_at !== null),
                IconColumn::make('two_factor_confirmed_at')->label('2FA')->boolean()->getStateUsing(fn ($record) => $record->two_factor_confirmed_at !== null),
                TextColumn::make('last_login_at')->label('Last sign-in')->since()->sortable(),
                TextColumn::make('created_at')->label('Joined')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $s) => [$s->value => ucfirst($s->value)])->all()),
                SelectFilter::make('roles')->relationship('roles', 'name')->label('Role'),
                TernaryFilter::make('email_verified_at')->label('Email verified')->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    UserActions::roles(),
                    UserActions::suspend(),
                    UserActions::reinstate(),
                ]),
            ]);
    }
}
