<?php

namespace App\Filament\Resources\MediaAssets\Tables;

use App\Domain\Media\Models\MediaAsset;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('variants')->withCount('usages'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('preview')
                    ->getStateUsing(fn (MediaAsset $record): ?string => $record->isImage() ? $record->url('sm') : null)
                    ->imageHeight(56),
                TextColumn::make('title')->searchable()->placeholder('Untitled')->description(fn (MediaAsset $record) => $record->filename),
                TextColumn::make('alt_text')
                    ->label('Alt text')
                    ->limit(40)
                    ->placeholder('Missing — please add')
                    ->color(fn (MediaAsset $record) => $record->alt_text || ! $record->isImage() ? null : 'warning')
                    ->getStateUsing(fn (MediaAsset $record): ?string => $record->isImage() ? $record->alt_text : '— (document)'),
                TextColumn::make('dimensions')->label('Type')->getStateUsing(fn (MediaAsset $record) => $record->isImage() ? "{$record->width} × {$record->height}" : 'PDF'),
                TextColumn::make('size')->formatStateUsing(fn (int $state) => number_format($state / 1024, 0).' KB')->sortable(),
                TextColumn::make('usages_count')->label('Used in')->badge()->sortable(),
                TextColumn::make('created_at')->label('Uploaded')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('alt_text')->label('Has alt text')->nullable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
