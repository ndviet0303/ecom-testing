<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ecommerce\CartService;
use App\Services\Ecommerce\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address_id' => ['required', 'integer', 'exists:addresses,id'],
            'shipping_zone_id' => ['required', 'integer', 'exists:shipping_zones,id'],
            'weight_grams' => ['required', 'integer', 'min:0'],
            'tax_rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'coupon_code' => ['nullable', 'string', 'max:32'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'in:immediate,sepay_qr'],
        ]);

        [$cart] = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Cart-Token'),
            false
        );

        if ($cart === null) {
            abort(400, 'Cart not found. Add items while authenticated or merge a guest cart first.');
        }

        $order = $this->checkoutService->checkout($request->user(), $cart, [
            'shipping_address_id' => (int) $validated['shipping_address_id'],
            'shipping_zone_id' => (int) $validated['shipping_zone_id'],
            'weight_grams' => (int) $validated['weight_grams'],
            'tax_rate_basis_points' => (int) $validated['tax_rate_basis_points'],
            'coupon_code' => $validated['coupon_code'] ?? null,
            'customer_note' => $validated['customer_note'] ?? null,
            'payment_method' => $validated['payment_method'] ?? 'immediate',
        ]);

        return response()->json([
            'message' => 'Order placed.',
            'order' => $order,
        ], 201);
    }
}
