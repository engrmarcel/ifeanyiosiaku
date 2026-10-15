<?php

namespace App\Filament\Resources\Redirects;

use App\Domain\Cms\Actions\DeleteRedirect;
use App\Domain\Cms\Actions\SaveRedirect;
use App\Domain\Cms\Models\Redirect;
use App\Filament\Resources\Pages\Pages\PageFormErrors;
use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Old addresses that send visitors (and search engines) somewhere else. Page renames create these
 * automatically; editors add their own here, e.g. for links printed on a flyer.
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('from_path')
                ->label('Old address')
                ->required()
                ->maxLength(151)
                ->prefix('/')
                ->formatStateUsing(fn (?string $state): string => ltrim((string) $state, '/'))
                ->dehydrateStateUsing(fn (?string $state): string => '/'.ltrim((string) $state, '/'))
                ->helperText('The address people may still visit, e.g. old-services.'),
            TextInput::make('to_path')
                ->label('Send visitors to')
                ->required()
                ->maxLength(255)
                ->helperText('A page path such as /services, or a full https:// address.'),
            Radio::make('status_code')
                ->label('Type')
                ->options([301 => 'Permanent (301) — the page has moved for good', 302 => 'Temporary (302) — it will come back'])
                ->default(301)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('from_path')->label('Old address')->searchable()->fontFamily('mono'),
                TextColumn::make('to_path')->label('Goes to')->searchable()->fontFamily('mono')->limit(60),
                TextColumn::make('status_code')->label('Type')->badge()
                    ->formatStateUsing(fn (int $state): string => $state === 302 ? 'Temporary' : 'Permanent'),
                IconColumn::make('is_automatic')->label('Automatic')->boolean()
                    ->tooltip('Created automatically when a page address changed.'),
                TextColumn::make('hits')->label('Visits')->numeric()->sortable(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->filters([TernaryFilter::make('is_automatic')->label('Automatic')])
            ->emptyStateHeading('No redirects yet')
            ->emptyStateDescription('Renaming a published page adds one automatically.')
            ->recordActions([
                EditAction::make()->using(fn (Redirect $record, array $data): Redirect => PageFormErrors::map(
                    fn () => app(SaveRedirect::class)->handle($record, $data, self::actor()),
                    PageFormErrors::MODAL,
                )),
                DeleteAction::make()->using(function (Redirect $record): bool {
                    app(DeleteRedirect::class)->handle($record, self::actor());

                    return true;
                }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }

    public static function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
