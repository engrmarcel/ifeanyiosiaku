<?php

namespace App\Filament\Resources\Portfolio;

use App\Domain\Cms\Blocks\Fields;
use App\Domain\Portfolio\Models\Testimonial;
use App\Filament\Resources\Portfolio\Pages\ManageTestimonials;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Real testimonials only; each is shown publicly only after the author's consent is confirmed. */
class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Portfolio';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('author_name')->label('Name')->required()->maxLength(120),
            TextInput::make('author_title')->label('Role / organisation')->maxLength(160),
            Textarea::make('quote')->required()->rows(4)->maxLength(1500)->columnSpanFull(),
            Fields::mediaById('avatar_media_id', 'Photo (optional)'),
            Toggle::make('consent_confirmed_at')
                ->label('The author agreed to this being published')
                ->helperText('Required before the testimonial can appear on the website.')
                ->formatStateUsing(fn ($state): bool => filled($state))
                ->dehydrateStateUsing(fn ($state, ?Testimonial $record) => $state ? ($record->consent_confirmed_at ?? now()) : null),
            Toggle::make('is_visible')->label('Show on the website')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('author_name')->label('Name')->searchable()->description(fn (Testimonial $r) => $r->author_title),
                TextColumn::make('quote')->limit(70),
                IconColumn::make('consent_confirmed_at')->label('Consent')->boolean()->getStateUsing(fn (Testimonial $r) => $r->consent_confirmed_at !== null),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
            ])
            ->emptyStateHeading('No testimonials yet')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTestimonials::route('/')];
    }
}
