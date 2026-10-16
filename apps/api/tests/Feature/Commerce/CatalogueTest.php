<?php

use App\Domain\Commerce\Enums\ProductType;
use App\Domain\Commerce\Models\Product;
use App\Domain\Commerce\Models\ProductFile;
use App\Domain\Commerce\Money;
use App\Domain\Content\Models\Category;
use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\SettingsRepository;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\FilesRelationManager;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function storeApi(string $path = '')
{
    return test()->withHeaders(test()->apiHeaders())->getJson('/api/v1/store/products'.$path);
}

it('handles money exactly in minor units', function () {
    expect(Money::toMinor('12,500.50', 'NGN'))->toBe(1_250_050)
        ->and(Money::toMinor('45', 'USD'))->toBe(4500)
        ->and(Money::toMajor(1_250_050, 'NGN'))->toBe('12500.50')
        ->and(Money::format(1_250_050, 'NGN'))->toBe('₦12,500.50')
        ->and(Money::format(4500, 'USD'))->toBe('$45.00');

    expect(fn () => Money::toMinor('12.5.0', 'NGN'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::toMinor('-5', 'NGN'))->toThrow(InvalidArgumentException::class);
});

it('lists published products with their price, featured first', function () {
    product();
    product(['slug' => 'power-bi-template', 'title' => 'Power BI template', 'type' => ProductType::Template, 'is_featured' => true], 500_000);
    product(['slug' => 'free-cheatsheet', 'title' => 'Free cheat sheet', 'is_free' => true]);
    product(['slug' => 'draft-product', 'status' => Product::STATUS_DRAFT]);
    product(['slug' => 'scheduled', 'published_at' => now()->addDay()]);

    $response = storeApi()->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.currency', 'NGN')
        ->assertJsonPath('data.0.slug', 'power-bi-template')
        ->assertJsonPath('data.0.price.formatted', '₦5,000.00');

    $free = collect($response->json('data'))->firstWhere('slug', 'free-cheatsheet');
    expect($free['isFree'])->toBeTrue()->and($free['price'])->toBeNull()->and($free['available'])->toBeTrue();

    expect(collect(storeApi('?type=template')->json('data'))->pluck('slug')->all())->toBe(['power-bi-template'])
        ->and(collect(storeApi('?q=handbook')->json('data'))->pluck('slug')->all())->toBe(['dax-handbook']);
});

it('shows products without a price in the chosen currency as unavailable', function () {
    app(SettingsRepository::class)->set('commerce.currencies', ['NGN', 'USD']);
    product();

    storeApi('?currency=USD')->assertJsonPath('meta.currency', 'USD')
        ->assertJsonPath('data.0.price', null)
        ->assertJsonPath('data.0.available', false);

    storeApi('?currency=EUR')->assertJsonPath('meta.currency', 'NGN'); // not on sale: falls back
});

it('shows what buyers get without exposing the private files', function () {
    Storage::fake('products');
    $book = product(['description' => '<p>Learn <strong>DAX</strong>.</p><script>alert(1)</script>', 'licence_terms' => 'Personal use only.']);
    $category = Category::query()->create(['type' => Category::TYPE_PRODUCT, 'name' => 'Power BI', 'slug' => 'power-bi']);
    $book->categories()->attach($category);
    $book->versions()->create(['version_label' => '2.0', 'released_at' => '2026-10-01', 'changelog' => 'New chapters.']);
    $book->files()->create(['label' => 'Handbook (PDF)', 'path' => $book->ulid.'/secret-name.pdf', 'original_name' => 'handbook.pdf', 'size' => 2_400_000]);
    $other = product(['slug' => 'related-one', 'title' => 'Related']);
    $other->categories()->attach($category);

    $data = storeApi('/dax-handbook')->assertOk()->json('data');

    expect($data['descriptionHtml'])->toBe('<p>Learn <strong>DAX</strong>.</p>')
        ->and($data['includes'])->toBe([['label' => 'Handbook (PDF)', 'size' => 2_400_000, 'extension' => 'pdf']])
        ->and($data['versions'][0]['label'])->toBe('2.0')
        ->and($data['licenceTerms'])->toBe('Personal use only.')
        ->and($data['related'][0]['slug'])->toBe('related-one')
        ->and(json_encode($data))->not->toContain('secret-name')->not->toContain($book->ulid.'/');

    storeApi('/missing')->assertNotFound();
});

it('lists the contents of a bundle', function () {
    $a = product();
    $b = product(['slug' => 'template', 'title' => 'Template'], 300_000);
    $bundle = product(['slug' => 'starter-bundle', 'title' => 'Starter bundle', 'type' => ProductType::Bundle], 1_400_000);
    $bundle->bundleItems()->attach([$a->id => ['sort_order' => 1], $b->id => ['sort_order' => 2]]);

    expect(collect(storeApi('/starter-bundle')->json('data.bundle'))->pluck('slug')->all())->toBe(['dax-handbook', 'template']);
});

it('disappears entirely while the Commerce add-on is off', function () {
    product();
    resolvePath('/store/dax-handbook')->assertJsonPath('data.status', 'page');
    expect(sitemapPaths())->toContain('/store/dax-handbook');

    app(SettingsRepository::class)->set('addons.commerce.enabled', false);

    storeApi()->assertNotFound()->assertJsonPath('error.code', 'addon_disabled');
    resolvePath('/store')->assertJsonPath('data.status', 'missing');
    resolvePath('/store/dax-handbook')->assertJsonPath('data.status', 'missing');
    expect(sitemapPaths())->not->toContain('/store/dax-handbook');

    $this->actingAs(contentStaff(Role::Admin))->get('/admin/products')->assertForbidden();
});

it('redirects the old address when a live product is renamed', function () {
    $book = product();
    $book->update(['slug' => 'the-dax-handbook']);

    resolvePath('/store/dax-handbook')->assertExactJson(['data' => ['status' => 'redirect', 'to' => '/store/the-dax-handbook', 'code' => 301]]);
});

it('lets store managers create products with exact prices', function () {
    $this->actingAs(contentStaff(Role::Admin));

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'title' => 'Excel to Power BI',
            'slug' => 'excel-to-power-bi',
            'type' => 'ebook',
            'status' => Product::STATUS_PUBLISHED,
            'prices' => [['currency' => 'NGN', 'amount' => '12,500.50', 'compare_at_amount' => '15000']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Product::query()->where('slug', 'excel-to-power-bi')->firstOrFail();
    expect($created->prices()->value('amount'))->toBe(1_250_050)
        ->and($created->prices()->value('compare_at_amount'))->toBe(1_500_000)
        ->and($created->isLive())->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'product.created']);

    Livewire::test(CreateProduct::class)
        ->fillForm(['title' => 'Zero', 'slug' => 'zero', 'type' => 'pdf', 'status' => 'draft', 'prices' => [['currency' => 'NGN', 'amount' => '0']]])
        ->call('create')
        ->assertHasFormErrors();

    Livewire::test(CreateProduct::class)
        ->fillForm(['title' => 'Freebie', 'slug' => 'freebie', 'type' => 'pdf', 'status' => 'draft', 'is_free' => true])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(Product::query()->where('slug', 'freebie')->firstOrFail()->prices()->count())->toBe(0);
});

it('stores uploaded product files privately and refuses unsafe types', function () {
    Storage::fake('products');
    $this->actingAs(contentStaff(Role::Admin));
    $book = product();
    $manager = fn () => Livewire::test(FilesRelationManager::class, ['ownerRecord' => $book, 'pageClass' => EditProduct::class]);

    $manager()->callAction(TestAction::make('create')->table(), [
        'label' => 'Handbook (PDF)',
        'path' => UploadedFile::fake()->createWithContent('handbook.pdf', "%PDF-1.4\n".str_repeat('x', 5000)),
    ])->assertHasNoFormErrors();

    $file = ProductFile::query()->sole();
    expect($file->original_name)->toBe('handbook.pdf')
        ->and($file->path)->toStartWith($book->ulid.'/')->not->toContain('handbook')
        ->and($file->size)->toBe(5009)
        ->and($file->checksum_sha256)->toHaveLength(64);
    Storage::disk('products')->assertExists($file->path);

    $manager()->callAction(TestAction::make('create')->table(), [
        'label' => 'Bad',
        'path' => UploadedFile::fake()->create('virus.exe', 10),
    ])->assertHasFormErrors(['path']);
});

it('keeps the store admin to staff with the commerce permission', function () {
    $this->actingAs(contentStaff(Role::Editor))->get('/admin/products')->assertForbidden();
    $this->actingAs(contentStaff(Role::Admin))->get('/admin/products')->assertOk();
    $this->actingAs(contentStaff(Role::Admin))->get('/admin/store-categories')->assertOk();
});
