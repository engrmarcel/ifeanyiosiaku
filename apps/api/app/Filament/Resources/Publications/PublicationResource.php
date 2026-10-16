<?php

namespace App\Filament\Resources\Publications;

use App\Domain\Cms\Blocks\Fields;
use App\Domain\Content\Models\Publication;
use App\Filament\Resources\Publications\Pages\CreatePublication;
use App\Filament\Resources\Publications\Pages\EditPublication;
use App\Filament\Resources\Publications\Pages\ListPublications;
use App\Filament\Resources\Publications\RelationManagers\VersionsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/** eBooks, research, papers and technical documents, each with a history of released versions. */
class PublicationResource extends Resource
{
    protected static ?string $model = Publication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Publication')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?Publication $record) {
                            if ($record === null && blank($get('slug'))) {
                                $set('slug', Str::limit(Str::slug((string) $state), 150, ''));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Web address')
                        ->prefix('/publications/')
                        ->required()
                        ->maxLength(150)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->rule(fn (?Publication $record) => Rule::unique('publications', 'slug')->ignore($record?->getKey())),
                    Select::make('type')->options(Publication::TYPES)->required()->default('paper'),
                    TagsInput::make('authors')
                        ->placeholder('Add an author and press Enter')
                        ->columnSpanFull()
                        ->helperText('In the order they should appear.'),
                    Textarea::make('abstract')->label('Abstract / description')->rows(6)->maxLength(5000)->columnSpanFull(),
                    TextInput::make('doi')->label('DOI (optional)')->maxLength(120)->placeholder('10.1234/abcd.5678')
                        ->regex('/^10\.\d{4,9}\/\S+$/'),
                    TextInput::make('isbn')->label('ISBN (optional)')->maxLength(20)
                        ->regex('/^(?:97[89][- ]?)?(?:\d[- ]?){9}[\dXx]$/'),
                ]),
            Section::make('Publishing')
                ->columnSpan(1)
                ->schema([
                    Radio::make('status')
                        ->options(['draft' => 'Draft (not visible)', 'published' => 'Published'])
                        ->default('draft')
                        ->required()
                        ->disabled(fn (): bool => ! self::canPublish()),
                    DateTimePicker::make('published_at')->label('Publish time')->seconds(false)
                        ->helperText('Leave empty to publish now. A future time schedules it.'),
                    Fields::mediaById('cover_media_id', 'Cover image'),
                ]),
            Section::make('Search & sharing')
                ->columnSpanFull()
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextInput::make('seo.title')->label('Search title')->maxLength(70),
                    Toggle::make('seo.noindex')->label('Hide from search engines'),
                    Textarea::make('seo.description')->label('Search description')->rows(2)->maxLength(170),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')->searchable()->limit(60)->description(fn (Publication $r) => '/publications/'.$r->slug),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => Publication::TYPES[$state] ?? $state),
                TextColumn::make('status')->badge()
                    ->getStateUsing(fn (Publication $r): string => match (true) {
                        $r->trashed() => 'In trash',
                        $r->status !== 'published' => 'Draft',
                        $r->published_at !== null && $r->published_at->isFuture() => 'Scheduled',
                        default => 'Published',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Published' => 'success', 'Scheduled' => 'info', 'In trash' => 'danger', default => 'gray',
                    }),
                TextColumn::make('versions_count')->counts('versions')->label('Versions'),
                TextColumn::make('updated_at')->label('Updated')->since(),
            ])
            ->filters([SelectFilter::make('type')->options(Publication::TYPES), TrashedFilter::make()])
            ->emptyStateHeading('No publications yet')
            ->recordActions([EditAction::make()]);
    }

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [VersionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublications::route('/'),
            'create' => CreatePublication::route('/create'),
            'edit' => EditPublication::route('/{record}/edit'),
        ];
    }

    public static function canPublish(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('publish', Publication::class);
    }

    /**
     * Server-side publishing rules shared by the create and edit screens.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepare(array $data, ?Publication $record): array
    {
        $status = $data['status'] ?? $record->status ?? 'draft';

        if ($status === 'published' && $record?->status !== 'published' && ! self::canPublish()) {
            throw ValidationException::withMessages(['data.status' => 'You do not have permission to publish.']);
        }

        if ($status === 'published' && blank($data['published_at'] ?? $record?->published_at)) {
            $data['published_at'] = now();
        }

        $data['authors'] = array_values(array_filter(array_map(fn ($a) => trim((string) $a), (array) ($data['authors'] ?? [])), fn ($a) => $a !== '' && mb_strlen($a) <= 120));

        return $data;
    }
}
