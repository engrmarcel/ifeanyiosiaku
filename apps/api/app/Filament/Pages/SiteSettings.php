<?php

namespace App\Filament\Pages;

use App\Domain\Captcha\CaptchaManager;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\Enums\Addon;
use App\Domain\Settings\SettingsRepository;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * @property-read Schema $form
 *
 * Runtime site settings. Every change goes through SettingsRepository, which records it in the audit log.
 * Only non-secret values live here; API keys stay in the server environment.
 */
class SiteSettings extends Page
{
    /** Settings managed on this page, with their value types. */
    private const KEYS = [
        'protection.allow_saving_and_inspect' => 'bool',
        'registration.enabled' => 'bool',
        'announcements.enabled' => 'bool',
        'security.require_staff_two_factor' => 'bool',
        'captcha.driver' => 'string',
        'addons.commerce.enabled' => 'bool',
        'addons.learning.enabled' => 'bool',
        'addons.community.enabled' => 'bool',
        'addons.rewards.enabled' => 'bool',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can(Permission::SettingsManage->value) ?? false;
    }

    public function mount(): void
    {
        $settings = app(SettingsRepository::class);
        $state = [];

        foreach (array_keys(self::KEYS) as $key) {
            $state[self::field($key)] = $settings->get($key);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $isSuperAdmin = Auth::user()?->hasRole(Role::SuperAdmin->value) ?? false;

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Add-on modules')
                    ->description('Switch optional parts of the platform on or off. A module that is off disappears from the website and its pages cannot be opened. Data is kept, so switching it back on restores everything.')
                    ->columns(2)
                    ->schema(array_map(
                        fn (Addon $addon) => Toggle::make(self::field($addon->settingKey()))
                            ->label($addon->label())
                            ->helperText($addon->description()),
                        Addon::cases(),
                    )),
                Section::make('Content protection')
                    ->description('Discourages visitors from saving images, right-clicking, or opening the browser\'s "Inspect" tools on the public website. Signed-in staff are not affected. This deters casual copying but cannot fully prevent it: screenshots and browser menus remain available.')
                    ->schema([
                        Toggle::make(self::field('protection.allow_saving_and_inspect'))
                            ->label('Allow visitors to save images and use Inspect')
                            ->helperText('Off (recommended): right-click, image dragging, long-press save and developer-tool shortcuts are blocked.'),
                    ]),
                Section::make('Membership')
                    ->schema([
                        Toggle::make(self::field('registration.enabled'))
                            ->label('Allow new members to register'),
                    ]),
                Section::make('Announcements')
                    ->schema([
                        Toggle::make(self::field('announcements.enabled'))
                            ->label('Show the homepage announcement popup')
                            ->helperText('Master switch. When off, no announcement is shown even if one is scheduled.'),
                    ]),
                Section::make('Security')
                    ->schema([
                        Toggle::make(self::field('security.require_staff_two_factor'))
                            ->label('Require two-factor authentication for staff')
                            ->helperText('Off by default while developing; on by default in production. Strongly recommended for the live site. Only a Super Administrator can change this.')
                            ->disabled(! $isSuperAdmin),
                        Select::make(self::field('captcha.driver'))
                            ->label('Form verification (CAPTCHA) provider')
                            ->options(fn (): array => $this->captchaOptions())
                            ->required()
                            ->disabled(! $isSuperAdmin)
                            ->helperText('A third-party provider can only be selected once its keys are set in the server environment.'),
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
            ->footer([
                Actions::make([
                    Action::make('save')->label('Save settings')->submit('save')->keyBindings(['mod+s']),
                ]),
            ]);
    }

    public function save(): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && $actor->can(Permission::SettingsManage->value), 403);

        $state = $this->form->getState();
        $settings = app(SettingsRepository::class);
        $superAdminOnly = ['security.require_staff_two_factor', 'captcha.driver'];

        foreach (self::KEYS as $key => $type) {
            if (in_array($key, $superAdminOnly, true) && ! $actor->hasRole(Role::SuperAdmin->value)) {
                continue;
            }

            if (! array_key_exists(self::field($key), $state)) {
                continue;
            }

            if ($type === 'bool') {
                $value = (bool) $state[self::field($key)];
            } else {
                $value = (string) $state[self::field($key)];

                if ($key === 'captcha.driver' && ! array_key_exists($value, $this->captchaOptions())) {
                    continue;
                }
            }

            if ($settings->get($key) !== $value) {
                $settings->set($key, $value, $actor);
            }
        }

        Notification::make()->title('Settings saved')->success()->send();
    }

    /**
     * Providers whose keys are configured; the first-party check needs none.
     *
     * @return array<string, string>
     */
    private function captchaOptions(): array
    {
        $options = ['first_party' => 'Built-in check (recommended, no account needed)'];

        if (config('captcha.recaptcha.site_key') && config('captcha.recaptcha.secret_key')) {
            $options['recaptcha'] = 'Google reCAPTCHA';
        }

        if (config('captcha.turnstile.site_key') && config('captcha.turnstile.secret_key')) {
            $options['turnstile'] = 'Cloudflare Turnstile';
        }

        return array_intersect_key($options, array_flip(CaptchaManager::DRIVERS));
    }

    /** Form field names cannot contain dots (they mean nesting), so map "a.b" to "a__b". */
    private static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
