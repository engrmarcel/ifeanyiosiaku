<?php

namespace Database\Seeders;

use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Actions\SetHomepage;
use App\Domain\Cms\Models\Page;
use App\Domain\Media\Actions\StoreMediaUpload;
use App\Domain\Media\MediaUsageRecorder;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Portfolio\Models\Skill;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\SettingsRepository;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Starting content from the owner's approved profile (PRODUCT_REQUIREMENTS §3): portrait, skills and
 * a homepage. Safe to re-run: it only creates what is missing and never overwrites edits.
 * Production: run once with `php artisan db:seed --class=InitialContentSeeder --force`.
 */
class InitialContentSeeder extends Seeder
{
    private const EXPERTISE = ['Software Development', 'Artificial Intelligence', 'Business Intelligence', 'Data Analytics', 'Data Visualization'];

    private const TECHNOLOGIES = ['Python', 'Qlik', 'Microsoft Power BI', 'Tableau'];

    public function run(): void
    {
        $portrait = $this->portrait();

        foreach (self::EXPERTISE as $i => $name) {
            Skill::query()->firstOrCreate(['group' => 'expertise', 'name' => $name], ['sort_order' => $i]);
        }

        foreach (self::TECHNOLOGIES as $i => $name) {
            Skill::query()->firstOrCreate(['group' => 'technology', 'name' => $name], ['sort_order' => $i]);
        }

        if (! Page::query()->withTrashed()->where('is_homepage', true)->exists() && ! Page::query()->withTrashed()->where('slug', 'home')->exists()) {
            $this->homepage($portrait);
        }
    }

    private function portrait(): ?MediaAsset
    {
        $settings = app(SettingsRepository::class);
        $existing = $settings->get('profile.portrait');

        if (is_string($existing) && MediaAsset::query()->where('ulid', $existing)->exists()) {
            return MediaAsset::query()->where('ulid', $existing)->first();
        }

        $source = base_path('../web/public/brand/ifeanyi-osiaku-portrait.jpg');

        if (! is_file($source)) {
            $this->command->warn('Portrait not found at apps/web/public/brand; skipping.');

            return null;
        }

        $copy = (string) tempnam(sys_get_temp_dir(), 'portrait');
        copy($source, $copy);

        $asset = app(StoreMediaUpload::class)->handle(
            new UploadedFile($copy, 'ifeanyi-osiaku-portrait.jpg', null, null, true),
            null,
            ['title' => 'Ifeanyi Osiaku — portrait', 'alt_text' => 'Portrait of Ifeanyi Osiaku'],
        );

        $settings->set('profile.portrait', $asset->ulid);
        $row = Setting::query()->where('key', 'profile.portrait')->firstOrFail();
        app(MediaUsageRecorder::class)->sync($row, 'portrait', [$asset->ulid]);

        return $asset;
    }

    private function homepage(?MediaAsset $portrait): void
    {
        $page = app(SavePageDraft::class)->handle(new Page, [
            'title' => 'Home',
            'slug' => 'home',
            'template' => 'landing',
            'seo' => [
                'title' => 'Ifeanyi Osiaku — Software, AI & Data',
                'description' => 'Software Developer, AI Enthusiast and Business Intelligence & Data Analytics Professional turning complex data into practical solutions.',
                'og_media' => $portrait?->ulid,
            ],
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'eyebrow' => '// software · ai · data',
                    'heading' => 'Ifeanyi Osiaku',
                    'subheading' => 'Software Developer | AI Enthusiast | Business Intelligence & Data Analytics Professional',
                    'body' => 'Turning technical knowledge and complex data into practical solutions to real-world problems — through software, artificial intelligence, business intelligence and data visualization.',
                    'primary_label' => 'About me',
                    'primary_url' => '#about',
                    'secondary_label' => 'Join the community',
                    'secondary_url' => '/register',
                    'media' => $portrait?->ulid,
                    'show_terminal' => true,
                    'terminal_lines' => [
                        ['kind' => 'command', 'text' => 'whoami'],
                        ['kind' => 'output', 'text' => 'ifeanyi-osiaku — software · ai · business intelligence'],
                        ['kind' => 'command', 'text' => 'cat stack.txt'],
                        ['kind' => 'highlight', 'text' => 'Python · Qlik · Power BI · Tableau'],
                        ['kind' => 'command', 'text' => 'echo $MISSION'],
                        ['kind' => 'output', 'text' => 'complex data → practical solutions'],
                    ],
                ]],
                ['type' => 'profile_intro', 'data' => ['eyebrow' => '01 // about', 'heading' => 'About me', 'show_portrait' => false, 'show_years' => true, 'full_bio' => false, 'anchor' => 'about']],
                ['type' => 'expertise', 'data' => ['eyebrow' => '02 // expertise', 'heading' => 'What I work with', 'intro' => 'Areas of expertise and the tools I use to turn data into decisions.', 'show_expertise' => true, 'show_technologies' => true, 'anchor' => 'expertise']],
                ['type' => 'projects', 'data' => ['eyebrow' => '03 // work', 'heading' => 'Selected work', 'selection' => 'featured', 'limit' => '6', 'anchor' => 'work']],
                ['type' => 'services', 'data' => ['eyebrow' => '04 // services', 'heading' => 'How I can help', 'anchor' => 'services']],
                ['type' => 'cta', 'data' => [
                    'eyebrow' => '// next step',
                    'heading' => 'Learn, build and grow with me',
                    'body' => 'Create a free account to follow courses, resources and community updates as they launch.',
                    'primary_label' => 'Create a free account',
                    'primary_url' => '/register',
                ]],
            ],
        ], null);

        $published = app(PublishPage::class)->handle($page, null);
        app(SetHomepage::class)->handle($published, null);
    }
}
