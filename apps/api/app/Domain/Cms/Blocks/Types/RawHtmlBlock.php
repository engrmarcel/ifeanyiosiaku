<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use App\Domain\Identity\Enums\Permission;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

/**
 * Trusted HTML/CSS/JavaScript (REQ-CMS-8), e.g. an interactive chart or a third-party widget.
 * Only staff with the cms.raw_html permission (Super Administrators) may add or change it, and
 * every change is audit-logged (SavePageDraft). It is never inserted into the page: the website
 * shows it in a sandboxed frame served from /api/v1/embeds with its own restrictive rules, so its
 * scripts cannot read visitors' sessions or change the rest of the site.
 */
class RawHtmlBlock extends Block
{
    public const MAX_LENGTH = 100_000;

    public static function type(): string
    {
        return 'raw_html';
    }

    public static function label(): string
    {
        return 'Custom code (HTML/CSS/JS)';
    }

    public static function icon(): string
    {
        return 'heroicon-o-command-line';
    }

    public static function description(): ?string
    {
        return 'Your own HTML, CSS and JavaScript in an isolated frame. Super Administrators only.';
    }

    public static function canEdit(): bool
    {
        return Auth::user()?->can(Permission::CmsRawHtml->value) ?? false;
    }

    public static function fields(): array
    {
        $locked = fn (): bool => ! self::canEdit();

        return [
            TextInput::make('title')
                ->label('Short description (for screen readers)')
                ->required()
                ->maxLength(120)
                ->placeholder('Interactive sales chart')
                ->disabled($locked)->dehydrated()
                ->helperText(fn (): string => self::canEdit()
                    ? 'The code runs in an isolated frame: it cannot see visitors\' accounts or change the rest of the page. External scripts (e.g. from a CDN) are allowed inside the frame.'
                    : 'Only a Super Administrator can add or change custom code. You can still move or hide this block.'),
            Textarea::make('html')
                ->label('HTML, CSS and JavaScript')
                ->required()
                ->rows(14)
                ->maxLength(self::MAX_LENGTH)
                ->extraInputAttributes(['style' => 'font-family: monospace'])
                ->disabled($locked)->dehydrated(),
            Radio::make('height_mode')
                ->label('Height')
                ->options(['auto' => 'Fit the content automatically', 'fixed' => 'Fixed height'])
                ->default('auto')
                ->inline()
                ->live()
                ->disabled($locked)->dehydrated(),
            TextInput::make('height')
                ->label('Height in pixels')
                ->numeric()->minValue(80)->maxValue(3000)->default(400)
                ->visible(fn (Get $get): bool => $get('height_mode') === 'fixed')
                ->disabled($locked)->dehydrated(),
        ];
    }

    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'html' => ['required', 'string', 'max:'.self::MAX_LENGTH],
            'height_mode' => ['nullable', 'in:auto,fixed'],
            'height' => ['nullable', 'integer', 'min:80', 'max:3000'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        $fixed = ($data['height_mode'] ?? 'auto') === 'fixed';

        return [
            'title' => (string) ($data['title'] ?? 'Embedded content'),
            'src' => $context->embedUrl((string) ($data['html'] ?? '')),
            'height' => $fixed ? (int) ($data['height'] ?? 400) : null,
        ];
    }

    /**
     * The parts of a block that only permitted staff may change.
     *
     * @param  array<string, mixed>  $data
     */
    public static function protectedFingerprint(array $data): string
    {
        // Normalised, so a form returning "400" instead of 400 does not count as a change.
        $mode = ($data['height_mode'] ?? null) === 'fixed' ? 'fixed' : 'auto';

        return hash('sha256', json_encode([
            (string) ($data['title'] ?? ''),
            str_replace("\r\n", "\n", (string) ($data['html'] ?? '')),
            $mode,
            $mode === 'fixed' && is_numeric($data['height'] ?? null) ? (int) $data['height'] : null,
        ]) ?: '');
    }
}
