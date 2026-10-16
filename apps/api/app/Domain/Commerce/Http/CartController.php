<?php

namespace App\Domain\Commerce\Http;

use App\Domain\Commerce\Cart\CartService;
use App\Domain\Commerce\Models\Coupon;
use App\Domain\Commerce\Models\Product;
use App\Domain\Commerce\ProductPresenter;
use App\Domain\Commerce\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The visitor's cart. Prices in every response are recalculated by the server. */
class CartController
{
    public function __construct(
        private readonly CartService $carts,
        private readonly ProductPresenter $presenter,
        private readonly Store $store,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request, $this->carts->find($request));
    }

    public function add(Request $request): JsonResponse
    {
        $product = $this->product($request);
        [$cart, $cookie] = $this->carts->findOrCreate($request);
        $this->carts->add($cart, $product);

        return $this->respond($request, $cart, $cookie);
    }

    public function remove(Request $request, string $product): JsonResponse
    {
        $cart = $this->carts->find($request);
        $item = Product::query()->where('ulid', $product)->first();

        if ($cart !== null && $item !== null) {
            $this->carts->remove($cart, $item);
        }

        return $this->respond($request, $cart);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $code = Coupon::normalise((string) $request->input('code'));

        if ($code === '' || strlen($code) > 40 || ! preg_match('/^[A-Z0-9\-_]+$/', $code)) {
            throw ValidationException::withMessages(['code' => 'Enter a valid code.']);
        }

        [$cart, $cookie] = $this->carts->findOrCreate($request);
        $cart->forceFill(['coupon_code' => $code])->save();

        $quote = $this->carts->quote($cart, $this->user($request));

        if ($quote->couponProblem !== null) {
            // Keep the cart clean: an unusable code is not kept.
            $cart->forceFill(['coupon_code' => null])->save();

            throw ValidationException::withMessages(['code' => $quote->couponProblem]);
        }

        return $this->respond($request, $cart, $cookie);
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->carts->find($request);
        $cart?->forceFill(['coupon_code' => null])->save();

        return $this->respond($request, $cart);
    }

    public function currency(Request $request): JsonResponse
    {
        [$cart, $cookie] = $this->carts->findOrCreate($request);
        $cart->forceFill(['currency' => $this->store->currency((string) $request->input('currency'))])->save();

        return $this->respond($request, $cart, $cookie);
    }

    private function product(Request $request): Product
    {
        $id = $request->input('product');
        $product = is_string($id) ? Product::query()->where('ulid', $id)->first() : null;

        if ($product === null) {
            throw ValidationException::withMessages(['product' => 'This product is not available.']);
        }

        return $product;
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private function respond(Request $request, mixed $cart, mixed $cookie = null): JsonResponse
    {
        $quote = $this->carts->quote($cart, $this->user($request));
        $response = response()->json(['data' => [
            ...$quote->toPublic($this->presenter),
            'count' => count($quote->lines),
            'currencies' => $this->store->currencies(),
        ]])->header('Cache-Control', 'no-store, private');

        return $cookie !== null ? $response->withCookie($cookie) : $response;
    }
}
