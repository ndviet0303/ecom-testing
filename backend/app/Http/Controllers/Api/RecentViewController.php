<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRecentView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecentViewController extends Controller
{
    private const LIST_LIMIT = 30;

    public function index(Request $request): JsonResponse
    {
        $rows = ProductRecentView::query()
            ->where('user_id', $request->user()->id)
            ->with(['product.inventory'])
            ->orderByDesc('viewed_at')
            ->limit(self::LIST_LIMIT)
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        ProductRecentView::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
            ],
            ['viewed_at' => now()]
        );

        return response()->json(['message' => 'Đã ghi nhận lượt xem.'], 201);
    }
}
