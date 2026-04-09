<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Ecommerce\OrderCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderCancelController extends Controller
{
    public function __construct(
        private readonly OrderCancellationService $orderCancellationService
    ) {}

    public function store(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->orderCancellationService->cancelByCustomer(
            $order,
            $request->user(),
            $validated['reason'] ?? null,
            $request->ip()
        );

        return response()->json($order->load(['orderItems', 'statusEvents']));
    }
}
