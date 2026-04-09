<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Coupon::query()->orderBy('code')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:coupons,code'],
            'min_subtotal_cents' => ['nullable', 'integer', 'min:0'],
            'discount_cents' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
        ]);

        $coupon = Coupon::query()->create([
            'code' => strtoupper($validated['code']),
            'min_subtotal_cents' => (int) ($validated['min_subtotal_cents'] ?? 0),
            'discount_cents' => $validated['discount_cents'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'expires_at' => $validated['expires_at'] ?? null,
            'max_uses' => $validated['max_uses'] ?? null,
            'max_uses_per_user' => $validated['max_uses_per_user'] ?? null,
        ]);

        return response()->json($coupon, 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:32', 'unique:coupons,code,'.$coupon->id],
            'min_subtotal_cents' => ['nullable', 'integer', 'min:0'],
            'discount_cents' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $coupon->update($validated);

        return response()->json($coupon->fresh());
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted.']);
    }
}
