<?php

namespace App\Filament\Pages;

use App\Domain\Cms\Support\SafeUrl;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Settings\Enums\AccentColour;
use App\Domain\Settings\Enums\Addon;
use App\Domain\Settings\Enums\SocialNetwork;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\SiteConfig;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * @property-read Schema $form
 *
 * Header and footer links, social profiles and the accent colour shown on every page of the website.
 */
class NavigationPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Navigation & footer';

    protected static ?string $title = 'Navigation, footer & appearance';

    protected static ?string $slug = 'navigation';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can(Permission::CmsManage->value) ?? false;
    }

    public function mount(): void
    {
        $settings = app(SettingsRepository::class);

        $this->form->fill([
            'header' => SiteConfig::links($settings->get('navigation.header'), SiteConfig::MAX_HEADER_LINKS),
            'footer' => SiteConfig::links($settings->get('navigation.footer'), SiteConfig::MAX_FOOTER_LINKS),
            'social' => SiteConfig::social($settings->get('social.links')),
            'footer_note' => $settings->get('site.footer_note'),
            'accent' => AccentColour::tryFrom((string) $settings->get('theme.accent'))->value ?? AccentColour::Green->value,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Header menu')
                    ->description('Links at the top of every page. On phones they open from a menu button. Use page paths such as /about, section links such as /#work, or full https:// addresses.')
                    ->schema([self::linksRepeater('header', SiteConfig::MAX_HEADER_LINKS)]),
                Section::make('Footer')
                    ->schema([
                        self::linksRepeater('footer', SiteConfig::MAX_FOOTER_LINKS),
                        TextInput::make('footer_note')->label('Footer line')->maxLength(120)
                            ->helperText('Short line shown beside your name, e.g. your areas of work.'),
                    ]),
                Section::make('Social profiles')
                    ->description('Only add accounts you own. They appear as icons in the footer and help search engines connect your profiles.')
                    ->schema([
                        Repeater::make('social')
                            ->hiddenLabel()
                            ->columns(2)
                            ->schema([
                                Select::make('network')->options(SocialNetwork::options())->required()->live(),
                                TextInput::make('url')
                                    ->label(fn (Get $get): string => $get('network') === SocialNetwork::Email->value ? 'Email address' : 'Profile address')
                                    ->required()
                                    ->maxLength(500)
                                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        $network = SocialNetwork::tryFrom((string) $get('network'));
                                        if ($network !== null && $network->normaliseUrl((string) $value) === null) {
                                            $fail($network === SocialNetwork::Email ? 'Enter a valid email address.' : 'Use the full https:// address of the profile.');
                                        }
                                    }),
                            ])
                            ->maxItems(SiteConfig::MAX_SOCIAL_LINKS)
                            ->reorderable()
                            ->addActionLabel('Add social profile')
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => SocialNetwork::tryFrom((string) ($state['network'] ?? ''))?->label()),
                    ]),
                Section::make('Appearance')
                    ->schema([
                        Radio::make('accent')
                            ->label('Accent colour')
                            ->options(AccentColour::options())
                            ->required()
                            ->helperText('Used for links, buttons and highlights. Every option is checked for readable contrast.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([Actions::make([Action::make('save')->label('Save')->submit('save')->keyBindings(['mod+s'])])]);
    }

    public function save(): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && $actor->can(Permission::CmsManage->value), 403);

        $state = $this->form->getState();
        $settings = app(SettingsRepository::class);
        $note = trim((string) ($state['footer_note'] ?? ''));

        $values = [
            'navigation.header' => SiteConfig::links($state['header'] ?? [], SiteConfig::MAX_HEADER_LINKS),
            'navigation.footer' => SiteConfig::links($state['footer'] ?? [], SiteConfig::MAX_FOOTER_LINKS),
            'social.links' => SiteConfig::social($state['social'] ?? []),
            'site.footer_note' => $note === '' ? null : $note,
            'theme.accent' => (AccentColour::tryFrom((string) ($state['accent'] ?? '')) ?? AccentColour::Green)->value,
        ];

        foreach ($values as $key => $value) {
            if ($settings->get($key) !== $value) {
                $settings->set($key, $value, $actor);
            }
        }

        Notification::make()->title('Saved — the website updates within a few seconds')->success()->send();
    }

    private static function linksRepeater(string $name, int $max): Repeater
    {
        return Repeater::make($name)
            ->hiddenLabel()
            ->columns(2)
            ->schema([
                TextInput::make('label')->required()->maxLength(40),
                TextInput::make('url')->label('Link')->required()->maxLength(500)->rule(new SafeUrl),
                Select::make('addon')
                    ->label('Only show while this add-on is on')
                    ->options(collect(Addon::cases())->mapWithKeys(fn (Addon $a) => [$a->value => $a->label()])->all())
                    ->placeholder('Always show'),
                Toggle::make('new_tab')->label('Open in a new tab')->inline(false),
            ])
            ->maxItems($max)
            ->reorderable()
            ->collapsible()
            ->defaultItems(0)
            ->addActionLabel('Add link')
            ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null) ? $state['label'].'  →  '.($state['url'] ?? '') : null);
    }
}
