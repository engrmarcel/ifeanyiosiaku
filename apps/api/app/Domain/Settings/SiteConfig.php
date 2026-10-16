<?php

namespace App\Domain\Settings;

use App\Domain\Cms\Jobs\RefreshWebsitePaths;
use App\Domain\Cms\Support\SafeUrl;
use App\Domain\Portfolio\Profile;
use App\Domain\Settings\Enums\AccentColour;
use App\Domain\Settings\Enums\Addon;
use App\Domain\Settings\Enums\SocialNetwork;
use App\Domain\Settings\Events\SettingChanged;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Site-wide presentation the website renders on every page: header and footer navigation, social
 * links, accent colour, search-engine indexing and the owner's identity for structured data.
 * Edited on Admin → Content → Navigation & footer.
 */
class SiteConfig
{
    public const MAX_HEADER_LINKS = 8;

    public const MAX_FOOTER_LINKS = 12;

    public const MAX_SOCIAL_LINKS = 10;

    /** Settings shown in the website's shared layout; a change refreshes it. */
    private const LAYOUT_SETTING_PREFIXES = ['navigation.', 'social.', 'theme.', 'seo.', 'site.', 'profile.', 'addons.', 'newsletter.'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly Addons $addons,
        private readonly Profile $profile,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPublic(): array
    {
        $profile = $this->profile->toPublic();
        $accent = AccentColour::tryFrom((string) $this->settings->get('theme.accent')) ?? AccentColour::Green;

        return [
            'name' => (string) ($this->settings->get('site.name') ?: $profile['name']),
            'tagline' => $this->settings->get('site.tagline') ?: null,
            'footerNote' => $this->settings->get('site.footer_note') ?: null,
            'year' => (int) now(config('platform.display_timezone'))->format('Y'),
            'navigation' => [
                'header' => $this->visibleLinks(self::links($this->settings->get('navigation.header'), self::MAX_HEADER_LINKS)),
                'footer' => $this->visibleLinks(self::links($this->settings->get('navigation.footer'), self::MAX_FOOTER_LINKS)),
            ],
            'social' => array_map(fn (array $link) => [
                'network' => $link['network'],
                'label' => SocialNetwork::from($link['network'])->label(),
                'url' => $link['url'],
            ], self::social($this->settings->get('social.links'))),
            'theme' => ['accent' => $accent->value, 'tokens' => $accent->tokens()],
            'indexing' => $this->indexing(),
            'newsletter' => [
                'enabled' => $this->settings->bool('newsletter.enabled'),
                'consentText' => (string) $this->settings->get('newsletter.consent_text'),
            ],
            'profile' => [
                'name' => $profile['name'],
                'headline' => $profile['headline'],
                'shortBio' => $profile['shortBio'],
                'portrait' => $profile['portrait'],
            ],
        ];
    }

    /** Whether search engines may index the website. */
    public function indexing(): bool
    {
        return $this->settings->get('seo.allow_indexing', true) !== false;
    }

    /**
     * Cleans stored or submitted navigation links: drops anything unsafe, incomplete or beyond the limit.
     *
     * @return list<array{label: string, url: string, new_tab: bool, addon: string|null}>
     */
    public static function links(mixed $value, int $max): array
    {
        $links = [];

        foreach (is_array($value) ? $value : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            $addon = Addon::tryFrom((string) ($item['addon'] ?? ''));

            if ($label === '' || mb_strlen($label) > 40 || $url === '' || strlen($url) > 500 || ! SafeUrl::isSafe($url)) {
                continue;
            }

            $links[] = ['label' => $label, 'url' => $url, 'new_tab' => (bool) ($item['new_tab'] ?? false), 'addon' => $addon?->value];

            if (count($links) === $max) {
                break;
            }
        }

        return $links;
    }

    /**
     * @return list<array{network: string, url: string}>
     */
    public static function social(mixed $value): array
    {
        $links = [];

        foreach (is_array($value) ? $value : [] as $item) {
            $network = is_array($item) ? SocialNetwork::tryFrom((string) ($item['network'] ?? '')) : null;
            $url = $network?->normaliseUrl((string) ($item['url'] ?? ''));

            if ($network === null || $url === null || strlen($url) > 500) {
                continue;
            }

            $links[] = ['network' => $network->value, 'url' => $url];

            if (count($links) === self::MAX_SOCIAL_LINKS) {
                break;
            }
        }

        return $links;
    }

    /** Refreshes the website's shared layout (and sitemap) when a setting it displays changes. */
    public function settingChanged(SettingChanged $event): void
    {
        foreach (self::LAYOUT_SETTING_PREFIXES as $prefix) {
            if (str_starts_with($event->key, $prefix)) {
                Cache::forget('cms:sitemap');

                try {
                    RefreshWebsitePaths::dispatch([], ['site']);
                } catch (Throwable $e) {
                    report($e);
                }

                return;
            }
        }
    }

    /**
     * Links belonging to a switched-off add-on are hidden (ADR-025).
     *
     * @param  list<array{label: string, url: string, new_tab: bool, addon: string|null}>  $links
     * @return list<array{label: string, url: string, newTab: bool}>
     */
    private function visibleLinks(array $links): array
    {
        $visible = [];

        foreach ($links as $link) {
            if ($link['addon'] !== null && ! $this->addons->enabled(Addon::from($link['addon']))) {
                continue;
            }

            $visible[] = ['label' => $link['label'], 'url' => $link['url'], 'newTab' => $link['new_tab']];
        }

        return $visible;
    }
}
