<?php

namespace App\Filament\Resources\Projects;

use App\Domain\Cms\Blocks\Fields;
use App\Domain\Cms\Support\SafeUrl;
use App\Domain\Portfolio\Models\Project;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

/** Portfolio projects and case studies (shown at /projects/{slug} and in "Portfolio projects" blocks). */
class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Portfolio';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project')
                ->columns(2)
                ->schema([
                    TextInput::make('title')->required()->maxLength(160)->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?Project $record) {
                            if ($record === null && blank($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')->label('Web address')->prefix('/projects/')->required()->maxLength(120)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true),
                    Textarea::make('summary')->rows(2)->maxLength(400)->columnSpanFull()
                        ->helperText('One or two sentences shown on project cards and in search results.'),
                    TextInput::make('role')->label('My role')->maxLength(160),
                    TextInput::make('client')->label('Client or organisation')->maxLength(160)
                        ->helperText('Only name clients you have permission to mention.'),
                    TextInput::make('period')->maxLength(60)->placeholder('2024'),
                    TagsInput::make('technologies')->placeholder('Add a technology (e.g. Power BI)')->columnSpanFull(),
                    Fields::mediaById('cover_media_id', 'Cover image'),
                ]),
            Section::make('Case study')
                ->schema([
                    Fields::richText('body', 'Story')->helperText('The challenge, your approach and the outcome. Only include results you can verify.'),
                    Repeater::make('links')
                        ->schema([
                            TextInput::make('label')->required()->maxLength(60),
                            TextInput::make('url')->required()->maxLength(500)->rule(new SafeUrl),
                        ])
                        ->columns(2)
                        ->maxItems(6)
                        ->defaultItems(0),
                ]),
            Section::make('Publishing')
                ->columns(2)
                ->schema([
                    Select::make('status')->options(['draft' => 'Draft (hidden)', 'published' => 'Published'])->default('draft')->required(),
                    DateTimePicker::make('published_at')->label('Publish date')->default(now())->seconds(false)
                        ->helperText('Future dates keep the project hidden until then.'),
                    Toggle::make('is_featured')->label('Feature on the homepage'),
                    TextInput::make('sort_order')->numeric()->default(0)->helperText('Lower numbers appear first.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('cover.variants'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('cover_preview')->label('')->getStateUsing(fn (Project $r) => $r->cover?->url('sm'))->imageHeight(48),
                TextColumn::make('title')->searchable()->description(fn (Project $r) => '/projects/'.$r->slug),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                IconColumn::make('is_featured')->label('Featured')->boolean(),
                TextColumn::make('published_at')->label('Publish date')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), RestoreAction::make()]);
    }

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
