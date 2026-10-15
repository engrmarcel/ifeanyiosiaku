<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->sortable()
                    ->description(fn (Page $record) => $record->path()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PageStatus $state) => $state->label())
                    ->color(fn (PageStatus $state) => $state->color()),
                IconColumn::make('is_homepage')->label('Homepage')->boolean()->trueIcon('heroicon-s-home')->falseIcon(''),
                TextColumn::make('published_at')->label('Last published')->since()->sortable()->placeholder('Never'),
                TextColumn::make('publish_at')->label('Scheduled for')->dateTime()->placeholder('—')->toggleable(),
                TextColumn::make('updated_at')->label('Edited')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(PageStatus::cases())->mapWithKeys(fn (PageStatus $s) => [$s->value => $s->label()])->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
            ]);
    }
}
