<?php

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Events\OrderPaid;
use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Models\Coupon;
use App\Domain\Commerce\Models\CouponRedemption;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\Product;
use App\Domain\Commerce\Orders\OrderTransitions;
use App\Domain\Commerce\Pricing\Pricer;
use App\Domain\Identity\Enums\Role;
use App\Domain\Settings\SettingsRepository;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function cartCall(string $method, string $path = '', array $data = [])
{
    return test()->withHeaders(test()->apiHeaders())->json($method, '/api/v1/store/cart'.$path, $data);
}

/** Carries the visitor's cart cookie into the following requests, as a browser would. */
function keepCart($response): void
{
    $cookie = $response->getCookie('io_cart');
    expect($cookie)->not->toBeNull();
    test()->withCredentials()->withCookie('io_cart', (string) $cookie->getValue());
}

function buyer(): User
{
    return User::factory()->create(['name' => 'Ada Obi', 'email' => 'ada@example.test'])->fresh();
}

function coupon(array $overrides = []): Coupon
{
    return Coupon::query()->create(['code' => 'launch10', 'type' => Coupon::PERCENT, 'value' => 10, ...$overrides]);
}

/** Puts products in the member's cart and checks out. */
function checkoutAs(User $user, array $products, ?string $code = null)
{
    test()->actingAs($user);
    foreach ($products as $product) {
        cartCall('POST', '/items', ['product' => $product->ulid])->assertOk();
    }
    if ($code !== null) {
        cartCall('POST', '/coupon', ['code' => $code])->assertOk();
    }

    return test()->withHeaders(test()->apiHeaders())->postJson('/api/v1/store/checkout');
}

it('keeps a visitor cart in a browser cookie and prices it on the server', function () {
    $product = product();

    cartCall('GET')->assertOk()->assertJsonPath('data.count', 0);

    $added = cartCall('POST', '/items', ['product' => $product->ulid])->assertOk()
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.total.amount', 1_250_000)
        ->assertJsonPath('data.purchasable', true)
        ->assertHeader('Cache-Control', 'no-store, private');
    keepCart($added);

    // Adding the same digital product again does not duplicate it.
    cartCall('POST', '/items', ['product' => $product->ulid])->assertJsonPath('data.count', 1);
    expect(Cart::query()->count())->toBe(1)
        ->and(Cart::query()->first()->guest_token_hash)->toHaveLength(64);

    // The price shown is always today's price, never a stored one.
    $product->prices()->update(['amount' => 990_000]);
    cartCall('GET')->assertJsonPath('data.total.amount', 990_000)->assertJsonPath('data.total.formatted', '₦9,900.00');

    cartCall('DELETE', '/items/'.$product->ulid)->assertJsonPath('data.count', 0);
});

it('refuses products that cannot be bought', function () {
    $draft = product(['slug' => 'draft', 'status' => Product::STATUS_DRAFT]);

    cartCall('POST', '/items', ['product' => $draft->ulid])->assertUnprocessable()->assertJsonPath('error.fields.product.0', 'This product is not available.');
    cartCall('POST', '/items', ['product' => 'not-a-product'])->assertUnprocessable();
});

it('moves the visitor cart into the account on sign-in', function () {
    $user = buyer();
    $kept = product(['slug' => 'kept']);
    Cart::query()->create(['user_id' => $user->id, 'currency' => 'NGN'])->items()->create(['product_id' => $kept->id, 'quantity' => 1]);

    $guestProduct = product();
    keepCart(cartCall('POST', '/items', ['product' => $guestProduct->ulid]));

    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/auth/login', ['email' => 'ada@example.test', 'password' => 'password'])->assertOk();

    $cart = Cart::query()->where('user_id', $user->id)->sole();
    expect($cart->items()->pluck('product_id')->sort()->values()->all())->toBe([$kept->id, $guestProduct->id])
        ->and(Cart::query()->whereNull('user_id')->count())->toBe(0);
});

it('calculates percentage and fixed discounts exactly', function () {
    $a = product(['slug' => 'a'], 1_000_000);
    $b = product(['slug' => 'b'], 500_000);
    $pricer = app(Pricer::class);

    coupon(['code' => 'TEN']);
    $quote = $pricer->quote(collect([$a, $b]), 'NGN', 'ten');
    expect($quote->couponProblem)->toBeNull()
        ->and($quote->discount())->toBe(150_000)
        ->and($quote->total())->toBe(1_350_000);

    // A fixed amount is shared in proportion to each price and never exceeds what is being paid.
    coupon(['code' => 'MINUS3K', 'type' => Coupon::FIXED, 'value' => 300_000, 'currency' => 'NGN']);
    $quote = $pricer->quote(collect([$a, $b]), 'NGN', 'MINUS3K');
    expect(array_map(fn ($l) => $l->discountAmount, $quote->lines))->toBe([200_000, 100_000]);

    coupon(['code' => 'HUGE', 'type' => Coupon::FIXED, 'value' => 9_000_000, 'currency' => 'NGN']);
    expect($pricer->quote(collect([$a, $b]), 'NGN', 'HUGE')->total())->toBe(0);

    // Rounding leftovers go to the last line, so the parts always add up to the coupon.
    $c = [product(['slug' => 'c1'], 100), product(['slug' => 'c2'], 100), product(['slug' => 'c3'], 100)];
    coupon(['code' => 'ONE', 'type' => Coupon::FIXED, 'value' => 100, 'currency' => 'NGN']);
    $quote = $pricer->quote(collect($c), 'NGN', 'ONE');
    expect(array_map(fn ($l) => $l->discountAmount, $quote->lines))->toBe([33, 33, 34]);

    // Product-specific codes only discount those products.
    coupon(['code' => 'ONLYB', 'value' => 50, 'applies_to' => [$b->id]]);
    expect($pricer->quote(collect([$a, $b]), 'NGN', 'ONLYB')->discount())->toBe(250_000);
});

it('explains why a code cannot be used', function () {
    $product = product();
    $other = product(['slug' => 'other']);
    $user = buyer();
    $pricer = app(Pricer::class);
    $problem = fn (string $code) => $pricer->quote(collect([$product]), 'NGN', $code, $user)->couponProblem;

    coupon(['code' => 'OFF', 'is_active' => false]);
    coupon(['code' => 'SOON', 'starts_at' => now()->addDay()]);
    coupon(['code' => 'OLD', 'ends_at' => now()->subMinute()]);
    coupon(['code' => 'USD', 'type' => Coupon::FIXED, 'value' => 500, 'currency' => 'USD']);
    coupon(['code' => 'BIG', 'min_subtotal' => 5_000_000]);
    coupon(['code' => 'ELSEWHERE', 'applies_to' => [$other->id]]);

    expect($problem('nope'))->toBe('This code is not valid.')
        ->and($problem('OFF'))->toBe('This code is not valid.')
        ->and($problem('SOON'))->toBe('This code is not valid.')
        ->and($problem('OLD'))->toBe('This code has expired.')
        ->and($problem('USD'))->toBe('This code only works for orders in USD.')
        ->and($problem('BIG'))->toBe('Your order does not reach the minimum amount for this code.')
        ->and($problem('ELSEWHERE'))->toBe('This code does not apply to the items in your cart.');

    // The cart does not keep a code it cannot use.
    $this->actingAs($user);
    cartCall('POST', '/items', ['product' => $product->ulid]);
    cartCall('POST', '/coupon', ['code' => 'OLD'])->assertUnprocessable()->assertJsonPath('error.fields.code.0', 'This code has expired.');
    cartCall('POST', '/coupon', ['code' => '<script>'])->assertUnprocessable();
    expect(Cart::query()->sole()->coupon_code)->toBeNull();
});

it('places an order from server prices only, and empties the cart', function () {
    Event::fake([OrderPaid::class]);
    $this->travelTo(now()->setDate(2026, 10, 16));
    $product = product();
    coupon();

    $response = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/store/checkout');
    $response->assertUnauthorized();

    // Whatever amounts a browser sends are ignored.
    $this->actingAs(buyer());
    cartCall('POST', '/items', ['product' => $product->ulid]);
    cartCall('POST', '/coupon', ['code' => 'Launch10']);
    $response = $this->withHeaders($this->apiHeaders())->postJson('/api/v1/store/checkout', ['total' => 1, 'billing_name' => 'Ada O. Obi'])
        ->assertCreated()
        ->assertJsonPath('data.next', 'pay')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.number', 'IO-2026-000001')
        ->assertJsonPath('data.subtotal.amount', 1_250_000)
        ->assertJsonPath('data.discount.amount', 125_000)
        ->assertJsonPath('data.total.amount', 1_125_000)
        ->assertJsonPath('data.items.0.title', 'The DAX Handbook');

    $order = Order::query()->sole();
    expect($order->billing_name)->toBe('Ada O. Obi')
        ->and($order->billing_email)->toBe('ada@example.test')
        ->and($order->expires_at->diffInMinutes(now(), true))->toEqualWithDelta(60, 1)
        ->and(CouponRedemption::query()->sole()->discount_amount)->toBe(125_000)
        ->and(Cart::query()->sole()->items()->count())->toBe(0)
        ->and(Cart::query()->sole()->coupon_code)->toBeNull();

    // Renaming or repricing the product later does not change the order.
    $product->update(['title' => 'Renamed']);
    $this->getJson('/api/v1/store/orders/'.$response->json('data.id'), $this->apiHeaders())
        ->assertJsonPath('data.items.0.title', 'The DAX Handbook')->assertJsonPath('data.total.amount', 1_125_000);

    // Order numbers keep counting.
    expect(checkoutAs(buyer2(), [product(['slug' => 'second'])])->json('data.number'))->toBe('IO-2026-000002');
    Event::assertNotDispatched(OrderPaid::class);
});

function buyer2(): User
{
    return User::factory()->create(['email' => 'chidi@example.test'])->fresh();
}

it('needs a confirmed email address and a cart that can still be bought', function () {
    $product = product();
    $unverified = User::factory()->unverified()->create();

    checkoutAs($unverified, [$product])->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['email']]]);

    $user = buyer();
    $this->actingAs($user);
    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/store/checkout')->assertUnprocessable()->assertJsonPath('error.fields.cart.0', 'Your cart is empty.');

    cartCall('POST', '/items', ['product' => $product->ulid]);
    $product->update(['status' => Product::STATUS_DRAFT]);
    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/store/checkout')
        ->assertUnprocessable()->assertJsonPath('error.fields.cart.0', 'Some items can no longer be bought. Please review your cart.');
    expect(Order::query()->count())->toBe(0);
});

it('completes free orders straight away', function () {
    Event::fake([OrderPaid::class]);
    $free = product(['slug' => 'free-guide', 'is_free' => true]);

    checkoutAs(buyer(), [$free])->assertCreated()->assertJsonPath('data.next', 'complete')->assertJsonPath('data.status', 'paid');

    // A 100% coupon also makes the order free.
    coupon(['code' => 'GIFT', 'value' => 100]);
    checkoutAs(buyer2(), [product()], 'gift')->assertJsonPath('data.next', 'complete')->assertJsonPath('data.total.amount', 0);

    Event::assertDispatchedTimes(OrderPaid::class, 2);
    expect(Order::query()->whereNotNull('paid_at')->count())->toBe(2);
});

it('enforces coupon use limits, and frees a use when an order is cancelled', function () {
    $product = product();
    coupon(['code' => 'ONCE', 'max_uses' => 1]);
    coupon(['code' => 'MINE', 'max_uses_per_user' => 1]);

    $first = checkoutAs(buyer(), [$product], 'once')->assertCreated();

    // Someone else cannot use the last remaining use.
    $other = buyer2();
    $this->actingAs($other);
    cartCall('POST', '/items', ['product' => $product->ulid]);
    cartCall('POST', '/coupon', ['code' => 'ONCE'])->assertUnprocessable()->assertJsonPath('error.fields.code.0', 'This code has been fully used.');

    // The code was valid when added to the cart, but used up before checkout: checkout refuses it.
    $race = User::factory()->create()->fresh();
    $this->actingAs($race);
    Coupon::query()->where('code', 'ONCE')->update(['max_uses' => 2]);
    cartCall('POST', '/items', ['product' => $product->ulid]);
    cartCall('POST', '/coupon', ['code' => 'ONCE'])->assertOk();
    Coupon::query()->where('code', 'ONCE')->update(['max_uses' => 1]);
    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/store/checkout')
        ->assertUnprocessable()->assertJsonPath('error.fields.coupon.0', 'This code has been fully used.');

    // Cancelling the first order releases its use.
    $this->actingAs(User::query()->where('email', 'ada@example.test')->sole());
    $this->withHeaders($this->apiHeaders())->postJson('/api/v1/store/orders/'.$first->json('data.id').'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->actingAs($other);
    cartCall('POST', '/coupon', ['code' => 'ONCE'])->assertOk()->assertJsonPath('data.discount.amount', 125_000);

    // Per-customer limits.
    checkoutAs($other, [], 'mine')->assertCreated();
    $this->actingAs($other);
    cartCall('POST', '/items', ['product' => $product->ulid]);
    cartCall('POST', '/coupon', ['code' => 'MINE'])->assertUnprocessable()->assertJsonPath('error.fields.code.0', 'You have already used this code.');
});

it('follows the order state machine', function () {
    Event::fake([OrderPaid::class]);
    expect(OrderStatus::Pending->canBecome(OrderStatus::Paid))->toBeTrue()
        ->and(OrderStatus::Paid->canBecome(OrderStatus::Cancelled))->toBeFalse()
        ->and(OrderStatus::Cancelled->canBecome(OrderStatus::Paid))->toBeFalse()
        ->and(OrderStatus::Expired->canBecome(OrderStatus::Paid))->toBeFalse()
        ->and(OrderStatus::Paid->canBecome(OrderStatus::Refunded))->toBeTrue();

    checkoutAs(buyer(), [product()])->assertCreated();
    $order = Order::query()->sole();
    $transitions = app(OrderTransitions::class);

    // A repeated payment confirmation changes nothing the second time.
    expect($transitions->markPaid($order, 'test'))->toBeTrue()
        ->and($transitions->markPaid($order, 'test'))->toBeFalse()
        ->and($transitions->expire($order))->toBeFalse();
    Event::assertDispatchedTimes(OrderPaid::class, 1);

    // Paid orders cannot be cancelled (they are refunded instead).
    $this->withHeaders($this->apiHeaders())->postJson("/api/v1/store/orders/{$order->ulid}/cancel")->assertUnprocessable();
    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(DB::table('audit_logs')->where('action', 'order.paid')->count())->toBe(1);
});

it('expires orders that are not paid in time', function () {
    coupon(['code' => 'ONCE', 'max_uses' => 1]);
    checkoutAs(buyer(), [product()], 'once')->assertCreated();

    $this->artisan('commerce:expire-orders')->assertSuccessful();
    expect(Order::query()->sole()->status)->toBe(OrderStatus::Pending);

    $this->travel(61)->minutes();
    $this->artisan('commerce:expire-orders')->assertSuccessful();

    $order = Order::query()->sole();
    expect($order->status)->toBe(OrderStatus::Expired)
        ->and($order->cancel_reason)->toBe('expired')
        ->and(CouponRedemption::query()->sole()->released_at)->not->toBeNull()
        ->and(app(OrderTransitions::class)->markPaid($order, 'late'))->toBeFalse();
});

it('shows customers only their own orders', function () {
    $mine = checkoutAs(buyer(), [product()])->json('data.id');
    $theirs = checkoutAs(buyer2(), [product(['slug' => 'other'])])->json('data.id');

    $this->actingAs(User::query()->where('email', 'ada@example.test')->sole());
    $this->getJson('/api/v1/store/orders', $this->apiHeaders())->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine);
    $this->getJson("/api/v1/store/orders/{$theirs}", $this->apiHeaders())->assertNotFound();
    $this->withHeaders($this->apiHeaders())->postJson("/api/v1/store/orders/{$theirs}/cancel")->assertNotFound();
    expect(Order::query()->where('ulid', $theirs)->sole()->status)->toBe(OrderStatus::Pending);
});

it('counts the coupon limit separately from ordinary cart use', function () {
    $product = product();
    coupon();
    $this->actingAs(buyer());

    foreach (range(1, 15) as $i) {
        cartCall('GET')->assertOk();
    }
    cartCall('POST', '/items', ['product' => $product->ulid])->assertOk();
    cartCall('POST', '/coupon', ['code' => 'LAUNCH10'])->assertOk();

    foreach (range(1, 9) as $i) {
        cartCall('POST', '/coupon', ['code' => 'LAUNCH10']);
    }
    cartCall('POST', '/coupon', ['code' => 'LAUNCH10'])->assertTooManyRequests();
    cartCall('GET')->assertOk();
});

it('closes the cart and checkout while the Commerce add-on is off', function () {
    $product = product();
    app(SettingsRepository::class)->set('addons.commerce.enabled', false);

    cartCall('GET')->assertNotFound();
    cartCall('POST', '/items', ['product' => $product->ulid])->assertNotFound();
    $this->actingAs(buyer())->withHeaders($this->apiHeaders())->postJson('/api/v1/store/checkout')->assertNotFound();
});

it('lets staff view orders, cancel unpaid ones, and manage coupons', function () {
    checkoutAs(buyer(), [product()])->assertCreated();
    $order = Order::query()->sole();

    $finance = contentStaff(Role::Finance);
    $this->actingAs($finance)->get('/admin/orders')->assertOk()->assertSee($order->number);
    $this->get("/admin/orders/{$order->id}")->assertOk()->assertSee('Ada Obi');
    Livewire::test(ViewOrder::class, ['record' => $order->id])->assertActionHidden('cancel');

    $admin = contentStaff(Role::Admin);
    $this->actingAs($admin);
    Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('cancel');
    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)->and($order->cancel_reason)->toBe('admin');

    Livewire::test(ManageCoupons::class)
        ->callAction(TestAction::make('create'), ['code' => 'easter-25', 'type' => Coupon::FIXED, 'value' => '2,500', 'currency' => 'NGN', 'is_active' => true])
        ->assertHasNoActionErrors();
    $coupon = Coupon::query()->sole();
    expect($coupon->code)->toBe('EASTER-25')->and($coupon->value)->toBe(250_000);

    Livewire::test(ManageCoupons::class)
        ->callAction(TestAction::make('create'), ['code' => 'bad', 'type' => Coupon::PERCENT, 'value' => '150'])
        ->assertHasActionErrors(['value']);

    $this->actingAs(contentStaff(Role::Editor))->get('/admin/orders')->assertForbidden();
});
