<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Domain\Cms\Blocks\BlockRegistry;
use App\Domain\Cms\Blocks\Fields;
use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Models\Page;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('page')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Content')
                        ->icon('heroicon-o-squares-plus')
                        ->schema([
                            app(BlockRegistry::class)->builder('blocks')->columnSpanFull(),
                        ]),
                    Tab::make('Page settings')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->columns(2)
                        ->schema([
                            TextInput::make('title')
                                ->required()
                                ->maxLength(200)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?Page $record) {
                                    if ($record === null && blank($get('slug'))) {
                                        $set('slug', Str::slug((string) $state));
                                    }
                                }),
                            TextInput::make('slug')
                                ->label('Web address')
                                ->prefix('/')
                                ->required()
                                ->maxLength(150)
                                ->helperText('Lowercase letters, numbers and hyphens. Changing the address of a published page automatically redirects the old one.'),
                            Select::make('template')
                                ->options(['default' => 'Standard page', 'landing' => 'Landing page (no page title header)'])
                                ->default('default'),
                        ]),
                    Tab::make('Search & sharing')
                        ->icon('heroicon-o-magnifying-glass')
                        ->columns(2)
                        ->schema([
                            TextInput::make('seo.title')->label('Search title')->maxLength(70)->helperText('Up to about 60 characters. Leave empty to use the page title.'),
                            Toggle::make('seo.noindex')->label('Hide from search engines'),
                            Textarea::make('seo.description')->label('Search description')->rows(2)->maxLength(170)->columnSpanFull()->helperText('About 150 characters summarising the page.'),
                            Fields::media('seo.og_media', 'Social sharing image'),
                            TextInput::make('seo.canonical_url')->label('Canonical URL (advanced)')->url()->maxLength(300),
                        ]),
                    Tab::make('Schedule')
                        ->icon('heroicon-o-calendar')
                        ->columns(2)
                        ->schema([
                            DateTimePicker::make('publish_at')
                                ->label('Publish automatically at')
                                ->seconds(false)
                                ->helperText('Saves as Scheduled; the page goes live at this time.')
                                ->disabled(fn (?Page $record) => $record?->status === PageStatus::Published),
                            DateTimePicker::make('unpublish_at')
                                ->label('Unpublish automatically at')
                                ->seconds(false)
                                ->after('publish_at'),
                        ]),
                ]),
        ]);
    }
}
