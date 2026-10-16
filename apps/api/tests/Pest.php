<?php

use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Models\Page;
use App\Domain\Commerce\Enums\ProductType;
use App\Domain\Commerce\Models\Product;
use App\Domain\Identity\Enums\Role;
use App\Domain\Portfolio\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

/** A published store product, priced in NGN unless $ngn is null (amount in kobo). */
function product(array $overrides = [], ?int $ngn = 1_250_000): Product
{
    $product = Product::query()->create([
        'type' => ProductType::Ebook,
        'slug' => 'dax-handbook',
        'title' => 'The DAX Handbook',
        'summary' => 'Practical DAX patterns.',
        'status' => Product::STATUS_PUBLISHED,
        'published_at' => now()->subDay(),
        ...$overrides,
    ]);

    if ($ngn !== null && ! $product->is_free) {
        $product->prices()->create(['currency' => 'NGN', 'amount' => $ngn]);
    }

    return $product;
}

function sitemapPaths(): array
{
    return collect(test()->withHeaders(test()->apiHeaders())->getJson('/api/v1/sitemap')->assertOk()->json('data.entries'))
        ->pluck('path')->all();
}

/** Builder state is keyed by item UUID inside Livewire. */
function builderState(array $blocks): array
{
    return collect($blocks)->mapWithKeys(fn (array $b) => [(string) Str::uuid() => $b])->all();
}
