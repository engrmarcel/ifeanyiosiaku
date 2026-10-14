<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use App\Domain\Audit\Models\AuditLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('action')->badge(),
                        TextEntry::make('created_at')->label('When')->dateTime(),
                        TextEntry::make('actor.email')->label('By')->placeholder('System / anonymous'),
                        TextEntry::make('subject_type')->label('Subject type')->placeholder('—'),
                        TextEntry::make('subject_id')->label('Subject ID')->placeholder('—'),
                        TextEntry::make('ip_address')->label('IP address')->placeholder('—'),
                        TextEntry::make('user_agent')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('request_id')->label('Request ID')->copyable()->placeholder('—'),
                        TextEntry::make('ulid')->label('Entry ID')->copyable(),
                    ]),
                Section::make('Details')
                    ->schema([
                        KeyValueEntry::make('context')
                            ->label('')
                            ->getStateUsing(fn (AuditLog $record): array => array_map(
                                fn (mixed $value): string => (string) json_encode($value),
                                $record->context ?? [],
                            ))
                            ->placeholder('No extra details'),
                    ]),
            ]);
    }
}
