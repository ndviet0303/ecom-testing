<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ecommerce\ReturnRequest\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReturnRequestController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = ReturnRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['order', 'orderItem.product'])
            ->latest()
            ->paginate(20);

        return response()->json($items);
    }

    public function show(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $this->authorize('view', $returnRequest);

        return response()->json($returnRequest->load(['order', 'orderItem.product']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ReturnRequest::class);

        $validated = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $item = OrderItem::query()->with('order')->findOrFail($validated['order_item_id']);
        $order = $item->order;

        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        if (! in_array($order->status, ['shipped', 'delivered'], true)) {
            throw ValidationException::withMessages([
                'order_item_id' => ['Đơn phải đã giao hoặc đang giao (shipped/delivered) mới được yêu cầu trả.'],
            ]);
        }

        if ($validated['quantity'] > $item->quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Số lượng trả không được vượt quá số lượng trong đơn.'],
            ]);
        }

        $reserved = (int) ReturnRequest::query()
            ->where('order_item_id', $item->id)
            ->whereIn('status', [
                ReturnStatus::Pending->value,
                ReturnStatus::Approved->value,
                ReturnStatus::Received->value,
            ])
            ->sum('quantity');

        $max = $item->quantity - $reserved;
        if ($validated['quantity'] > $max) {
            throw ValidationException::withMessages([
                'quantity' => ['Đã có yêu cầu trả đang xử lý cho mặt hàng này.'],
            ]);
        }

        $rr = ReturnRequest::query()->create([
            'user_id' => $request->user()->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'quantity' => $validated['quantity'],
            'reason' => $validated['reason'] ?? null,
            'status' => ReturnStatus::Pending,
        ]);

        $this->auditLogger->log($request->user(), 'return_request.created', $rr, [
            'order_item_id' => $item->id,
            'quantity' => $validated['quantity'],
        ], $request->ip());

        return response()->json($rr->load(['order', 'orderItem.product']), 201);
    }
}
