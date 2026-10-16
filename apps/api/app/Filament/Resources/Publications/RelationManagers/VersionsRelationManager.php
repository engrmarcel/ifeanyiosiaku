<?php

namespace App\Filament\Resources\Publications\RelationManagers;

use App\Domain\Cms\Blocks\Fields;
use App\Domain\Content\Models\PublicationVersion;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Released versions: a PDF from the media library and/or a link to the publisher's page. */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Versions';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('version_label')
                ->label('Version')
                ->required()
                ->maxLength(40)
                ->placeholder('1.0 or 2nd edition')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('publication_id', $this->getOwnerRecord()->getKey())),
            DatePicker::make('released_at')->label('Release date')->required()->default(now()),
            Fields::documentById('file_media_id', 'PDF file'),
            TextInput::make('external_url')
                ->label('Or a link (e.g. publisher page)')
                ->url()
                ->maxLength(500)
                ->required(fn (Get $get): bool => blank($get('file_media_id')))
                ->validationMessages(['required' => 'Add a PDF file or a link, so visitors can get this version.'])
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && ! str_starts_with(strtolower((string) $value), 'https://')) {
                        $fail('Use a full https:// address.');
                    }
                }),
            Textarea::make('changelog')->label('What changed')->rows(3)->maxLength(3000)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('released_at', 'desc')
            ->columns([
                TextColumn::make('version_label')->label('Version'),
                TextColumn::make('released_at')->label('Released')->date(),
                TextColumn::make('file.title')->label('File')->placeholder('—'),
                TextColumn::make('external_url')->label('Link')->limit(40)->placeholder('—'),
                TextColumn::make('changelog')->limit(60)->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()->label('Add version')])
            ->emptyStateHeading('No versions yet')
            ->emptyStateDescription('Add the first version with its PDF or link.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription(fn (PublicationVersion $record) => "Remove version {$record->version_label}? The PDF stays in the media library."),
            ]);
    }
}
