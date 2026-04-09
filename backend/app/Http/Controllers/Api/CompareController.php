<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCompareItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompareController extends Controller
{
    private const MAX_ITEMS = 20;

    public function index(Request $request): JsonResponse
    {
        $items = ProductCompareItem::query()
            ->where('user_id', $request->user()->id)
            ->with(['product.inventory'])
            ->orderBy('id')
            ->get();

        return response()->json($items);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $count = ProductCompareItem::query()->where('user_id', $request->user()->id)->count();
        if ($count >= self::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'product' => ['Danh sách so sánh tối đa '.self::MAX_ITEMS.' sản phẩm.'],
            ]);
        }

        ProductCompareItem::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return response()->json(['message' => 'Đã thêm vào so sánh.'], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        ProductCompareItem::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json(['message' => 'Đã xóa khỏi so sánh.']);
    }
}
