<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->with('product')
            ->orderByDesc('id')
            ->get();

        return response()->json($items);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        Wishlist::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return response()->json(['message' => 'Added to wishlist.'], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json(['message' => 'Removed from wishlist.']);
    }
}
