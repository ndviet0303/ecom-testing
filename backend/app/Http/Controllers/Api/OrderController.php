<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('orderItems')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['orderItems', 'statusEvents']);

        return response()->json($order);
    }
}
