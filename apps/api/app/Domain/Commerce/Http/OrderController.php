<?php

namespace App\Domain\Commerce\Http;

use App\Domain\Commerce\Cart\CartService;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Orders\Checkout;
use App\Domain\Commerce\Orders\OrderTransitions;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Checkout and the signed-in customer's own orders. */
class OrderController
{
    public function __construct(
        private readonly CartService $carts,
        private readonly Checkout $checkout,
        private readonly OrderTransitions $transitions,
    ) {}

    /** Places an order from the cart. The response says whether payment is still needed. */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cart = $this->carts->find($request);

        if ($cart === null) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $order = $this->checkout->place($user, $cart, (string) $request->input('billing_name', $user->name), $request);

        return response()->json(['data' => [...$order->toPublic(), 'next' => $order->status->isPaid() ? 'complete' : 'pay']], 201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $orders = Order::query()->where('user_id', $user->getKey())->with('items')->latest('id')->limit(100)->get();

        return response()->json(['data' => $orders->map(fn (Order $o) => $o->toPublic())->values()->all()])
            ->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, string $order): JsonResponse
    {
        return response()->json(['data' => $this->own($request, $order)->toPublic()])
            ->header('Cache-Control', 'no-store, private');
    }

    public function cancel(Request $request, string $order): JsonResponse
    {
        $model = $this->own($request, $order);
        /** @var User $user */
        $user = $request->user();
        $this->transitions->cancel($model, 'customer', $user);

        return response()->json(['data' => $model->refresh()->toPublic()]);
    }

    /** Someone else's order is reported as not found, never as forbidden (no guessing of order ids). */
    private function own(Request $request, string $ulid): Order
    {
        $order = Order::query()->where('ulid', $ulid)->first();
        abort_if($order === null || $order->user_id !== $request->user()?->getKey(), 404);

        return $order;
    }
}
