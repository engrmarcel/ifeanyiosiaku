<?php

use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Models\Page;
use App\Domain\Identity\Enums\Role;
use App\Domain\Portfolio\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests run against the MySQL/MariaDB testing database (phpunit.xml) so that locking,
| constraints and transactions behave as in production.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
| Helpers shared by several test files.
*/

function resolvePath(string $path)
{
    return test()->withHeaders(test()->apiHeaders())->getJson('/api/v1/page-resolve?path='.urlencode($path));
}

function livePage(string $slug): Page
{
    $page = app(SavePageDraft::class)->handle(new Page, ['title' => ucfirst($slug), 'slug' => $slug, 'blocks' => []], null);

    return app(PublishPage::class)->handle($page, null);
}

function contentStaff(Role $role = Role::Editor): User
{
    return User::factory()->withTwoFactor()->role($role)->create()->fresh();
}

function project(array $overrides = []): Project
{
    return Project::query()->create([
        'slug' => 'sales-dashboard',
        'title' => 'Sales dashboard',
        'summary' => 'Regional revenue at a glance.',
        'technologies' => ['Power BI', 'DAX'],
        'status' => 'published',
        'published_at' => now()->subDay(),
        'is_featured' => true,
        ...$overrides,
    ]);
}
