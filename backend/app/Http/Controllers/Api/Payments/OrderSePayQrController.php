<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\SePayQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderSePayQrController extends Controller
{
    public function __construct(
        private readonly SePayQrService $sePayQrService
    ) {}

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        if ($order->status !== 'pending') {
            abort(422, 'Chỉ đơn chờ thanh toán (pending) mới có QR SePay.');
        }

        return response()->json([
            'qr_image_url' => $this->sePayQrService->qrImageUrl($order),
            'amount' => $order->total_cents,
            'transfer_content' => $this->sePayQrService->transferContent($order),
            'order_number' => $order->order_number,
        ]);
    }
}
