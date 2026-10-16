<?php

namespace App\Domain\Commerce\Cart;

use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Models\Product;
use App\Domain\Commerce\Pricing\Pricer;
use App\Domain\Commerce\Pricing\Quote;
use App\Domain\Commerce\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Finds and changes carts. Signed-in members have one cart; visitors get one per browser, found
 * through a random cookie (only its hash is stored). A visitor's cart joins their account cart
 * when they sign in.
 */
class CartService
{
    public const COOKIE = 'io_cart';

    public const COOKIE_DAYS = 30;

    public const MAX_ITEMS = 50;

    public function __construct(
        private readonly Pricer $pricer,
        private readonly Store $store,
    ) {}

    /** The current cart, or null when the visitor has none yet. */
    public function find(Request $request): ?Cart
    {
        $user = $request->user();

        if ($user instanceof User) {
            return Cart::query()->where('user_id', $user->getKey())->first();
        }

        $token = $request->cookie(self::COOKIE);

        return is_string($token) && strlen($token) === 40
            ? Cart::query()->whereNull('user_id')->where('guest_token_hash', hash('sha256', $token))->first()
            : null;
    }

    /**
     * The current cart, created if needed. The cookie is returned when a visitor's cart was created.
     *
     * @return array{0: Cart, 1: Cookie|null}
     */
    public function findOrCreate(Request $request): array
    {
        $cart = $this->find($request);

        if ($cart !== null) {
            return [$cart, null];
        }

        $user = $request->user();

        if ($user instanceof User) {
            return [Cart::query()->firstOrCreate(['user_id' => $user->getKey()], ['currency' => $this->store->defaultCurrency()]), null];
        }

        $token = Str::random(40);
        $cart = Cart::query()->create(['guest_token_hash' => hash('sha256', $token), 'currency' => $this->store->defaultCurrency()]);

        return [$cart, self::cookie($token)];
    }

    public function add(Cart $cart, Product $product): void
    {
        if (! $product->isLive()) {
            throw ValidationException::withMessages(['product' => 'This product is not available.']);
        }

        if ($cart->items()->count() >= self::MAX_ITEMS) {
            throw ValidationException::withMessages(['product' => 'Your cart is full.']);
        }

        // Digital products: one of each is enough.
        $cart->items()->firstOrCreate(['product_id' => $product->getKey()], ['quantity' => 1]);
        $cart->touch();
    }

    public function remove(Cart $cart, Product $product): void
    {
        $cart->items()->where('product_id', $product->getKey())->delete();
        $cart->touch();
    }

    /** Prices for what is in the cart right now (never stored, always recalculated). */
    public function quote(?Cart $cart, ?User $user): Quote
    {
        $currency = $this->store->currency($cart?->currency);

        if ($cart === null) {
            return new Quote($currency, []);
        }

        $products = $cart->items()->with(['product.prices', 'product.cover.variants', 'product.categories'])->get()
            ->map(fn ($item) => $item->product)->filter()->values();

        return $this->pricer->quote($products, $currency, $cart->coupon_code, $user);
    }

    /** On sign-in, the visitor's cart is added to the member's cart and then removed. */
    public function mergeIntoUser(Request $request, User $user): void
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || strlen($token) !== 40) {
            return;
        }

        $guest = Cart::query()->whereNull('user_id')->where('guest_token_hash', hash('sha256', $token))->first();

        if ($guest === null) {
            return;
        }

        DB::transaction(function () use ($guest, $user) {
            $cart = Cart::query()->firstOrCreate(['user_id' => $user->getKey()], ['currency' => $guest->currency]);

            foreach ($guest->items()->pluck('product_id') as $productId) {
                $cart->items()->firstOrCreate(['product_id' => $productId], ['quantity' => 1]);
            }

            if ($cart->coupon_code === null && $guest->coupon_code !== null) {
                $cart->forceFill(['coupon_code' => $guest->coupon_code])->save();
            }

            $guest->delete();
        });
    }

    public static function cookie(string $token): Cookie
    {
        return cookie(self::COOKIE, $token, self::COOKIE_DAYS * 24 * 60, '/', null, null, true, false, 'lax');
    }

    public static function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE);
    }
}
