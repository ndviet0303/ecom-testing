<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /** Danh sách mã đang hoạt động (storefront). */
    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('code')
            ->get(['id', 'code', 'min_subtotal_cents', 'discount_cents', 'expires_at']);

        return response()->json($coupons);
    }

    /** Kiểm tra nhanh một mã với subtotal gợi ý (không áp dụng vào giỏ). */
    public function preview(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'subtotal_cents' => ['required', 'integer', 'min:0'],
        ]);

        $coupon = Coupon::query()
            ->whereRaw('lower(code) = ?', [strtolower($code)])
            ->first();

        if ($coupon === null || ! $coupon->isUsableAt(now())) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired coupon.'], 422);
        }

        $subtotal = (int) $request->query('subtotal_cents');
        $eligible = $subtotal >= $coupon->min_subtotal_cents;

        return response()->json([
            'valid' => true,
            'eligible' => $eligible,
            'min_subtotal_cents' => $coupon->min_subtotal_cents,
            'discount_cents' => $coupon->discount_cents,
        ]);
    }
}
