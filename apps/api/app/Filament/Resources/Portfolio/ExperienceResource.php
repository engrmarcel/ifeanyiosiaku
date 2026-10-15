<?php

namespace App\Filament\Resources\Portfolio;

use App\Domain\Portfolio\Models\ExperienceEntry;
use App\Filament\Resources\Portfolio\Pages\ManageExperience;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Work history. Starts empty: nothing is shown until real entries are added. */
class ExperienceResource extends Resource
{
    protected static ?string $model = ExperienceEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Portfolio';

    protected static ?string $navigationLabel = 'Experience';

    protected static ?string $modelLabel = 'experience entry';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Role')->required()->maxLength(160),
            TextInput::make('organisation')->maxLength(160),
            TextInput::make('location')->maxLength(120),
            Toggle::make('is_current')->label('I currently work here')->live(),
            DatePicker::make('start_date')->native(false)->displayFormat('M Y'),
            DatePicker::make('end_date')->native(false)->displayFormat('M Y')->hidden(fn (Get $get) => (bool) $get('is_current'))->after('start_date'),
            Textarea::make('description')->rows(4)->maxLength(2000)->columnSpanFull(),
            Toggle::make('is_visible')->label('Show on the website')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')->label('Role')->searchable(),
                TextColumn::make('organisation')->placeholder('—'),
                TextColumn::make('period')->getStateUsing(fn (ExperienceEntry $r) => $r->period())->placeholder('—'),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
            ])
            ->emptyStateHeading('No experience added yet')
            ->emptyStateDescription('The Experience section stays hidden on the website until entries are added.')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageExperience::route('/')];
    }
}
