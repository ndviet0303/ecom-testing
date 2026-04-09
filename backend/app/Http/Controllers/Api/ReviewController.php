<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function forProduct(Product $product): JsonResponse
    {
        $reviews = Review::query()
            ->where('product_id', $product->id)
            ->with('user:id,name')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($reviews);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $purchased = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', function ($q) use ($request): void {
                $q->where('user_id', $request->user()->id)
                    ->whereIn('status', ['paid', 'packed', 'shipped', 'delivered']);
            })
            ->exists();

        if (! $purchased) {
            return response()->json([
                'message' => 'You can only review products you have purchased.',
            ], 422);
        }

        if (Review::query()->where('user_id', $request->user()->id)->where('product_id', $product->id)->exists()) {
            return response()->json(['message' => 'You already reviewed this product.'], 409);
        }

        $review = Review::query()->create([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
            'order_id' => $validated['order_id'] ?? null,
            'rating' => $validated['rating'],
            'body' => $validated['body'] ?? null,
        ]);

        return response()->json($review->load('user:id,name'), 201);
    }
}
