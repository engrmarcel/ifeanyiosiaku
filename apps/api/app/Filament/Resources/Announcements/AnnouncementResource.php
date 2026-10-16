<?php

namespace App\Filament\Resources\Announcements;

use App\Domain\Announcements\Models\Announcement;
use App\Domain\Cms\Blocks\Fields;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Popup announcements with their schedule, audience and results (shown, clicked, closed). */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Message')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('title')->required()->maxLength(120)->columnSpanFull(),
                    Textarea::make('message')->required()->rows(3)->maxLength(1000)->columnSpanFull(),
                    Fields::mediaById('media_id', 'Image (optional)'),
                    TextInput::make('campaign')->label('Campaign tag (optional)')->maxLength(60)->helperText('For your own reporting, e.g. "launch-2026".'),
                    TextInput::make('cta_label')->label('Button text')->maxLength(60)->requiredWith('cta_url'),
                    Fields::link('cta_url', 'Button link')->requiredWith('cta_label'),
                ]),
            Section::make('When and where')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')
                        ->label('Active')
                        ->helperText('Also needs "Show the homepage announcement popup" switched on in Site settings.')
                        ->columnSpanFull(),
                    DateTimePicker::make('starts_at')->label('Show from')->seconds(false),
                    DateTimePicker::make('ends_at')->label('Show until')->seconds(false)->after('starts_at'),
                    Select::make('audience')->options(Announcement::AUDIENCES)->default('everyone')->required(),
                    Select::make('placement')->label('Pages')->options(Announcement::PLACEMENTS)->default('all')->required(),
                    Select::make('frequency')->label('How often')->options(Announcement::FREQUENCIES)->default('once')->required(),
                    TextInput::make('priority')
                        ->numeric()->minValue(0)->maxValue(100)->default(0)
                        ->helperText('If several are running, the highest priority is shown.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'events as impressions' => fn ($q) => $q->where('type', 'impression'),
                'events as clicks' => fn ($q) => $q->where('type', 'click'),
                'events as dismissals' => fn ($q) => $q->where('type', 'dismiss'),
            ]))
            ->columns([
                TextColumn::make('title')->searchable()->limit(50)->description(fn (Announcement $r) => $r->campaign),
                TextColumn::make('state')->label('Status')->badge()
                    ->getStateUsing(fn (Announcement $r) => $r->state())
                    ->color(fn (string $state) => match ($state) {
                        'Running' => 'success', 'Scheduled' => 'info', 'Ended' => 'gray', default => 'warning',
                    }),
                TextColumn::make('starts_at')->label('From')->dateTime()->placeholder('—'),
                TextColumn::make('ends_at')->label('Until')->dateTime()->placeholder('—'),
                TextColumn::make('impressions')->label('Shown')->numeric(),
                TextColumn::make('clicks')->label('Clicked')->numeric(),
                TextColumn::make('click_rate')->label('Click rate')
                    ->getStateUsing(fn (Announcement $r) => self::rate((int) $r->getAttribute('clicks'), (int) $r->getAttribute('impressions'))),
                TextColumn::make('dismissals')->label('Closed')->numeric(),
            ])
            ->emptyStateHeading('No announcements yet')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function rate(int $part, int $whole): string
    {
        return $whole > 0 ? number_format($part / $whole * 100, 1).'%' : '—';
    }

    public static function getPages(): array
    {
        return ['index' => ManageAnnouncements::route('/')];
    }
}
