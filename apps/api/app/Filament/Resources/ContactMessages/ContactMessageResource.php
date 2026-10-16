<?php

namespace App\Filament\Resources\ContactMessages;

use App\Domain\Contact\Models\ContactMessage;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Messages from the website's contact form. Reply by email; mark spam to keep the inbox clean. */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Inbox';

    protected static ?string $navigationLabel = 'Contact messages';

    public static function getNavigationBadge(): ?string
    {
        $count = ContactMessage::query()->where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('subject')->placeholder('—')->columnSpanFull(),
                    // Visitor text is shown as plain text (escaped), never as HTML.
                    TextEntry::make('message')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-wrap']),
                    TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => ContactMessage::STATUSES[$state] ?? $state),
                    TextEntry::make('created_at')->label('Received')->dateTime(),
                    TextEntry::make('page')->label('Sent from page')->placeholder('—'),
                    TextEntry::make('handler.name')->label('Handled by')->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (ContactMessage $r) => $r->email),
                TextColumn::make('subject')->placeholder('—')->limit(40)->searchable(),
                TextColumn::make('message')->limit(60)->searchable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ContactMessage::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'warning', 'replied' => 'success', 'spam' => 'danger', default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Received')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ContactMessage::STATUSES)->default(null),
            ])
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription('Messages sent through the contact form appear here.')
            ->recordActions([ViewAction::make(), DeleteAction::make()]);
    }

    /** Actions shared by the list and the message view. */
    public static function statusAction(string $status, string $label, Heroicon $icon): Action
    {
        return Action::make('mark_'.$status)
            ->label($label)
            ->icon($icon)
            ->color($status === 'spam' ? 'danger' : 'gray')
            ->visible(fn (ContactMessage $record) => $record->status !== $status)
            ->action(fn (ContactMessage $record) => self::setStatus($record, $status));
    }

    public static function setStatus(ContactMessage $message, string $status): void
    {
        $user = Auth::user();
        $message->forceFill([
            'status' => $status,
            'handled_by' => $user instanceof User ? $user->getKey() : null,
            'handled_at' => now(),
        ])->save();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof ContactMessage ? ($record->subject ?: 'Message from '.$record->name) : 'Message';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
