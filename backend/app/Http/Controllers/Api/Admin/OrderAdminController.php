<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Ecommerce\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Ecommerce\OrderTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderAdminController extends Controller
{
    public function __construct(
        private readonly OrderTransitionService $orderTransitionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['user', 'orderItems'])
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return response()->json($orders);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $to = OrderStatus::tryFrom($validated['status']);

        if ($to === null) {
            abort(422, 'Invalid status value.');
        }

        $order = $this->orderTransitionService->transition(
            $order,
            $to,
            $request->user(),
            $request->ip()
        );

        return response()->json($order->load(['orderItems', 'statusEvents']));
    }

    public function updateFulfillment(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:128'],
            'tracking_carrier' => ['sometimes', 'nullable', 'string', 'max:64'],
            'internal_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $payload = [];
        foreach (['tracking_number', 'tracking_carrier', 'internal_note'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if ($payload !== []) {
            $order->update($payload);
        }

        return response()->json($order->fresh()->load(['orderItems', 'statusEvents']));
    }
}
