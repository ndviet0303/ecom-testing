<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Ecommerce\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Ecommerce\OrderTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderAdminController extends Controller
{
    public function __construct(
        private readonly OrderTransitionService $orderTransitionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['user', 'orderItems', 'shippingAddress'])
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

        return response()->json($order->load(['user', 'shippingAddress', 'orderItems', 'statusEvents']));
    }

    public function updateFulfillment(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:128'],
            'tracking_carrier' => ['sometimes', 'nullable', 'string', 'max:64'],
            'internal_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['required_with:items', 'integer', 'distinct'],
            'items.*.serial_number' => ['nullable', 'string', 'max:128'],
        ]);

        $payload = [];
        foreach (['tracking_number', 'tracking_carrier', 'internal_note'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        DB::transaction(function () use ($order, $payload, $validated): void {
            if ($payload !== []) {
                $order->update($payload);
            }

            $items = $validated['items'] ?? [];
            if ($items === []) {
                return;
            }

            $itemIds = collect($items)->pluck('id')->values();
            $orderItems = $order->orderItems()
                ->whereIn('id', $itemIds)
                ->get()
                ->keyBy('id');

            if ($orderItems->count() !== $itemIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['Một số item không thuộc đơn hàng này.'],
                ]);
            }

            foreach ($items as $itemPayload) {
                $item = $orderItems->get($itemPayload['id']);
                $serialNumber = $itemPayload['serial_number'] ?? null;
                $warrantyMonths = (int) ($item?->product?->warranty_months ?? 0);
                $item?->update([
                    'serial_number' => $serialNumber,
                    'warranty_expires_at' => $serialNumber && $warrantyMonths > 0
                        ? now()->addMonths($warrantyMonths)
                        : null,
                ]);
            }
        });

        return response()->json($order->fresh()->load(['user', 'shippingAddress', 'orderItems', 'statusEvents']));
    }
}
