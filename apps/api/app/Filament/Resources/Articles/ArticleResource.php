<?php

namespace App\Filament\Resources\Articles;

use App\Domain\Cms\Blocks\Fields;
use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Tag;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Articles\RelationManagers\RevisionsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
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
use UnitEnum;

/**
 * Technical articles written in Markdown. Draft → preview → publish; a future publish time
 * schedules the article. Every save is kept in the version history.
 */
class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Article')
                ->columnSpan(2)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(200)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?Article $record) {
                            if ($record === null && blank($get('slug'))) {
                                $set('slug', Str::limit(Str::slug((string) $state), 150, ''));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Web address')
                        ->prefix('/articles/')
                        ->required()
                        ->maxLength(150)
                        ->regex(self::SLUG_PATTERN)
                        ->rule(fn (?Article $record) => Rule::unique('articles', 'slug')->ignore($record?->getKey()))
                        ->helperText('Lowercase letters, numbers and hyphens. Changing it after publishing redirects the old address automatically.'),
                    Textarea::make('excerpt')
                        ->label('Summary')
                        ->rows(2)
                        ->maxLength(500)
                        ->helperText('One or two sentences shown in article lists and search results.'),
                    MarkdownEditor::make('body')
                        ->label('Article text (Markdown)')
                        ->required()
                        ->maxLength(200_000)
                        ->fileAttachments(false)
                        ->toolbarButtons([
                            ['bold', 'italic', 'strike', 'link'],
                            ['heading'],
                            ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                            ['table'],
                            ['undo', 'redo'],
                        ])
                        ->helperText(str('Use `## Heading` for sections (they form the contents list). For highlighted code, start a block with three backticks and the language, e.g. ```python title="etl.py"; supported: '.implode(', ', array_keys(config('cms.code_languages'))).'. Images: copy an image link from the media library: ![description](/media/…).')->toString()),
                ]),
            Section::make('Publishing')
                ->columnSpan(1)
                ->schema([
                    Radio::make('status')
                        ->options([Article::STATUS_DRAFT => 'Draft (not visible)', Article::STATUS_PUBLISHED => 'Published'])
                        ->default(Article::STATUS_DRAFT)
                        ->required()
                        ->disabled(fn (): bool => ! self::canPublish())
                        ->helperText(fn (): ?string => self::canPublish() ? null : 'An editor with publishing rights makes articles live.'),
                    DateTimePicker::make('published_at')
                        ->label('Publish time')
                        ->seconds(false)
                        ->helperText('Leave empty to publish now. A future time schedules the article.'),
                    Fields::mediaById('cover_media_id', 'Cover image'),
                    Select::make('categories')
                        ->relationship('categories', 'name', fn (Builder $query) => $query->where('type', Category::TYPE_ARTICLE))
                        ->multiple()
                        ->preload()
                        ->createOptionForm([TextInput::make('name')->required()->maxLength(80)])
                        ->createOptionUsing(fn (array $data): int => self::category((string) $data['name'])->getKey()),
                    Select::make('tags')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->preload()
                        ->createOptionForm([TextInput::make('name')->required()->maxLength(60)])
                        ->createOptionUsing(fn (array $data): int => self::tag((string) $data['name'])->getKey()),
                ]),
            Section::make('Search & sharing')
                ->columnSpanFull()
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextInput::make('seo.title')->label('Search title')->maxLength(70)->helperText('Leave empty to use the article title.'),
                    Toggle::make('seo.noindex')->label('Hide from search engines'),
                    Textarea::make('seo.description')->label('Search description')->rows(2)->maxLength(170)->helperText('Leave empty to use the summary.'),
                    Fields::mediaById('seo.og_media', 'Social sharing image (defaults to the cover)'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('categories'))
            ->columns([
                TextColumn::make('title')->searchable()->limit(60)->description(fn (Article $r) => '/articles/'.$r->slug),
                TextColumn::make('status')->badge()
                    ->getStateUsing(fn (Article $r): string => match (true) {
                        $r->trashed() => 'In trash',
                        $r->status !== Article::STATUS_PUBLISHED => 'Draft',
                        $r->published_at !== null && $r->published_at->isFuture() => 'Scheduled',
                        default => 'Published',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Published' => 'success', 'Scheduled' => 'info', 'In trash' => 'danger', default => 'gray',
                    }),
                TextColumn::make('categories.name')->badge()->label('Categories'),
                TextColumn::make('reading_minutes')->label('Read')->suffix(' min'),
                TextColumn::make('published_at')->label('Publish time')->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([Article::STATUS_DRAFT => 'Draft', Article::STATUS_PUBLISHED => 'Published']),
                SelectFilter::make('categories')->relationship('categories', 'name')->multiple(),
                TrashedFilter::make(),
            ])
            ->emptyStateHeading('No articles yet')
            ->emptyStateDescription('Write your first article in Markdown, preview it, then publish.')
            ->recordActions([EditAction::make()]);
    }

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [RevisionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }

    public static function canPublish(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('publish', Article::class);
    }

    public static function category(string $name): Category
    {
        return Category::query()->firstOrCreate(
            ['type' => Category::TYPE_ARTICLE, 'slug' => Str::limit(Str::slug($name), 100, '') ?: 'category'],
            ['name' => trim($name)],
        );
    }

    public static function tag(string $name): Tag
    {
        return Tag::query()->firstOrCreate(
            ['slug' => Str::limit(Str::slug($name), 80, '') ?: 'tag'],
            ['name' => trim($name)],
        );
    }
}
