<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Domain\Audit\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('actor'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('actor.email')->label('By')->placeholder('System / anonymous')->searchable(),
                TextColumn::make('subject_type')->label('Subject')
                    ->formatStateUsing(fn (?string $state, AuditLog $record): string => $state ? class_basename($state).' #'.$record->subject_id : '—'),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_id')->label('Request ID')->toggleable(isToggledHiddenByDefault: true)->copyable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(fn (): array => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all())
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
