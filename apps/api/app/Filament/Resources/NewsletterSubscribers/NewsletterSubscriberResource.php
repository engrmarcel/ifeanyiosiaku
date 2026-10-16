<?php

namespace App\Filament\Resources\NewsletterSubscribers;

use App\Domain\Newsletter\Models\NewsletterSubscriber;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * People who signed up for the newsletter. Only "Subscribed" people confirmed by email and may be
 * sent newsletters. Deleting someone erases their record entirely (e.g. on request).
 */
class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Inbox';

    protected static ?string $navigationLabel = 'Newsletter subscribers';

    public const STATUSES = [
        NewsletterSubscriber::PENDING => 'Awaiting confirmation',
        NewsletterSubscriber::SUBSCRIBED => 'Subscribed',
        NewsletterSubscriber::UNSUBSCRIBED => 'Unsubscribed',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        NewsletterSubscriber::SUBSCRIBED => 'success', NewsletterSubscriber::PENDING => 'warning', default => 'gray',
                    }),
                TextColumn::make('consent_source')->label('Signed up from')->placeholder('—'),
                TextColumn::make('confirmed_at')->label('Confirmed')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('unsubscribed_at')->label('Unsubscribed')->dateTime()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('First signed up')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(self::STATUSES)])
            ->emptyStateHeading('No subscribers yet')
            ->emptyStateDescription('Turn on the sign-up form in System → Site settings → Newsletter once email sending is configured.')
            ->recordActions([
                DeleteAction::make()
                    ->label('Erase')
                    ->modalDescription('Permanently removes this person and their consent record, for example when they ask to be forgotten.'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListNewsletterSubscribers::route('/')];
    }
}
