<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Ecommerce\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService
    ) {}

    public function show(Request $request): JsonResponse
    {
        [$cart, $guestToken] = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Cart-Token'),
            false
        );

        if ($cart === null) {
            return response()->json([
                'cart_id' => null,
                'items' => [],
                'subtotal_cents' => 0,
            ]);
        }

        $cart->load(['items.product.inventory']);

        $response = response()->json([
            'cart_id' => $cart->id,
            'items' => $cart->items,
            'subtotal_cents' => $this->cartService->subtotalCents($cart),
        ]);

        if ($guestToken !== null) {
            $response->header('X-Cart-Token', $guestToken);
        }

        return $response;
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        [$cart, $guestToken] = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Cart-Token'),
            true
        );

        $product = Product::query()->findOrFail($validated['product_id']);
        $item = $this->cartService->addOrUpdateLine($cart, $product, (int) $validated['quantity'], true);

        $cart->load(['items.product']);

        $response = response()->json([
            'message' => 'Cart updated.',
            'item' => $item,
            'subtotal_cents' => $this->cartService->subtotalCents($cart),
        ], 201);

        if ($guestToken !== null) {
            $response->header('X-Cart-Token', $guestToken);
        }

        return $response;
    }

    public function addBulkItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        [$cart, $guestToken] = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Cart-Token'),
            true
        );

        $this->cartService->addMultipleLines($cart, $validated['items']);

        $cart->load(['items.product']);

        $response = response()->json([
            'message' => 'Cart updated in bulk.',
            'items' => $cart->items,
            'subtotal_cents' => $this->cartService->subtotalCents($cart),
        ], 201);

        if ($guestToken !== null) {
            $response->header('X-Cart-Token', $guestToken);
        }

        return $response;
    }

    public function updateItem(Request $request, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        [$cart, $guestToken] = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Cart-Token'),
            true
        );

        $product = Product::query()->findOrFail($productId);
        $item = $this->cartService->addOrUpdateLine($cart, $product, (int) $validated['quantity']);

        $cart->load(['items.product']);

        $response = response()->json([
            'message' => 'Cart updated.',
            'item' => $item,
            'subtotal_cents' => $this->cartService->subtotalCents($cart),
        ]);

        if ($guestToken !== null) {
            $response->header('X-Cart-Token', $guestToken);
        }

        return $response;
    }

    public function removeItem(Request $request, int $productId): JsonResponse
    {
        [$cart, $guestToken] = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Cart-Token'),
            true
        );

        $this->cartService->removeLine($cart, $productId);

        $response = response()->json([
            'message' => 'Item removed.',
            'subtotal_cents' => $this->cartService->subtotalCents($cart->fresh()->load('items')),
        ]);

        if ($guestToken !== null) {
            $response->header('X-Cart-Token', $guestToken);
        }

        return $response;
    }

    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_token' => ['required', 'uuid'],
        ]);

        $this->cartService->mergeGuestIntoUser($validated['guest_token'], $request->user());

        return response()->json(['message' => 'Guest cart merged.']);
    }
}
