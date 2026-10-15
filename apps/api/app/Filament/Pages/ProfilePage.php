<?php

namespace App\Filament\Pages;

use App\Domain\Cms\Blocks\Fields;
use App\Domain\Cms\Support\RichTextSanitizer;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Media\MediaUsageRecorder;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\SettingsRepository;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
 * The public profile shown in "Profile introduction" blocks and search-engine data.
 */
class ProfilePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Portfolio';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Profile';

    protected static ?string $title = 'Public profile';

    protected static ?string $slug = 'profile';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can(Permission::PortfolioManage->value) ?? false;
    }

    public function mount(): void
    {
        $settings = app(SettingsRepository::class);

        $this->form->fill([
            'name' => $settings->get('profile.name'),
            'headline' => $settings->get('profile.headline'),
            'short_bio' => $settings->get('profile.short_bio'),
            'bio' => $settings->get('profile.bio'),
            'years_experience' => $settings->get('profile.years_experience'),
            'portrait' => $settings->get('profile.portrait'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(80),
                        TextInput::make('years_experience')
                            ->label('Years of experience')
                            ->numeric()->minValue(0)->maxValue(60)
                            ->helperText('Shown only where a section allows it. Leave empty to hide.'),
                        TextInput::make('headline')->maxLength(160)->columnSpanFull(),
                        Fields::media('portrait', 'Portrait'),
                    ]),
                Section::make('Biography')
                    ->description('Write only what is true and verifiable. Empty fields are simply not shown.')
                    ->schema([
                        Textarea::make('short_bio')->label('Short biography')->rows(3)->maxLength(600),
                        Fields::richText('bio', 'Full biography (optional)'),
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
            ->footer([Actions::make([Action::make('save')->label('Save profile')->submit('save')->keyBindings(['mod+s'])])]);
    }

    public function save(): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && $actor->can(Permission::PortfolioManage->value), 403);

        $state = $this->form->getState();
        $settings = app(SettingsRepository::class);
        $years = filled($state['years_experience'] ?? null) ? (int) $state['years_experience'] : null;

        $values = [
            'profile.name' => trim((string) $state['name']),
            'profile.headline' => filled($state['headline'] ?? null) ? trim((string) $state['headline']) : null,
            'profile.short_bio' => filled($state['short_bio'] ?? null) ? trim((string) $state['short_bio']) : null,
            'profile.bio' => app(RichTextSanitizer::class)->clean($state['bio'] ?? null) ?: null,
            'profile.years_experience' => $years,
            'profile.portrait' => filled($state['portrait'] ?? null) ? (string) $state['portrait'] : null,
        ];

        foreach ($values as $key => $value) {
            if ($settings->get($key) !== $value) {
                $settings->set($key, $value, $actor);
            }
        }

        // Protect the chosen portrait from being deleted in the media library.
        $row = Setting::query()->firstOrCreate(['key' => 'profile.portrait'], ['value' => $values['profile.portrait']]);
        app(MediaUsageRecorder::class)->sync($row, 'portrait', array_filter([$values['profile.portrait']]));

        Notification::make()->title('Profile saved')->success()->send();
    }
}
