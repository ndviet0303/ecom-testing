<?php

namespace App\Services\Payments;

use App\Domain\Ecommerce\Exception\InvalidOrderTransitionException;
use App\Domain\Ecommerce\Order\OrderStateMachine;
use App\Domain\Ecommerce\Order\OrderStatus;
use App\Models\Order;
use App\Models\ProcessedWebhookEvent;
use App\Services\AuditLogger;
use App\Services\Ecommerce\OrderStatusEventRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SePayWebhookHandler
{
    public function __construct(
        private readonly OrderStateMachine $orderStateMachine,
        private readonly OrderStatusEventRecorder $orderStatusEventRecorder,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{processed: bool, order_id: int|null, message: string}
     */
    public function handle(array $payload): array
    {
        // Hỗ trợ cả payload phẳng (Bank Webhook) và payload lồng (Gateway IPN)
        $id = $payload['transaction']['id'] ?? $payload['id'] ?? null;
        if ($id === null) {
            return ['processed' => false, 'order_id' => null, 'message' => 'Thiếu id giao dịch.'];
        }

        $eventId = 'sepay:'.$id;

        if (ProcessedWebhookEvent::query()->where('provider', 'sepay')->where('event_id', $eventId)->exists()) {
            return ['processed' => true, 'order_id' => null, 'message' => 'Giao dịch webhook đã xử lý.'];
        }

        $transferType = $payload['transaction']['transaction_type'] ?? $payload['transferType'] ?? 'in';
        if (! in_array(strtolower((string) $transferType), ['in', 'payment', 'approved'])) {
             // Với Gateway IPN, transaction_type thường là PAYMENT hoặc notification_type là ORDER_PAID
             // Ta kiểm tra thêm notification_type nếu cần, nhưng thường id là đủ duy nhất.
        }

        // Kiểm tra số tài khoản nếu cần (thường chỉ áp dụng cho Bank Webhook)
        if (config('sepay.verify_account_number', true)) {
            $expected = config('sepay.account_number');
            $incoming = preg_replace('/\s+/', '', (string) ($payload['accountNumber'] ?? ''));
            if ($expected !== null && $expected !== '' && $incoming !== '') {
                $exp = preg_replace('/\s+/', '', (string) $expected);
                if ($incoming !== $exp) {
                    return ['processed' => true, 'order_id' => null, 'message' => 'Số tài khoản không khớp cấu hình.'];
                }
            }
        }

        $amount = (int) ($payload['transaction']['transaction_amount'] ?? $payload['transferAmount'] ?? 0);
        $content = (string) ($payload['transaction']['transaction_description'] ?? $payload['content'] ?? '');
        $orderIdFromPayload = $payload['order']['order_id'] ?? null;

        $order = $this->findMatchingPendingOrder($amount, $content, $orderIdFromPayload);

        if ($order === null) {
            return ['processed' => true, 'order_id' => null, 'message' => 'Không tìm thấy đơn chờ thanh toán khớp số tiền và nội dung/ID.'];
        }

        $orderIdOut = null;
        $message = 'Đã xác nhận thanh toán.';

        DB::transaction(function () use ($order, $payload, $eventId, $amount, &$orderIdOut, &$message): void {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = OrderStatus::tryFrom($order->status);

            if ($from !== OrderStatus::Pending) {
                $message = 'Đơn không còn trạng thái chờ thanh toán.';

                return;
            }

            try {
                $this->orderStateMachine->assertCanTransition($from, OrderStatus::Paid);
            } catch (InvalidOrderTransitionException) {
                $message = 'Không thể chuyển trạng thái đơn.';

                return;
            }

            $order->update(['status' => OrderStatus::Paid->value]);

            $this->orderStatusEventRecorder->record(
                $order,
                $from->value,
                OrderStatus::Paid->value,
                'system',
                null,
                [
                    'sepay_transaction_id' => $payload['transaction']['id'] ?? $payload['id'] ?? null,
                    'reference_code' => $payload['transaction']['reference_number'] ?? $payload['referenceCode'] ?? null,
                ]
            );

            $this->auditLogger->log(null, 'order.sepay_payment_confirmed', $order, [
                'sepay_id' => $payload['transaction']['id'] ?? $payload['id'] ?? null,
                'transfer_amount' => $amount,
            ], null);

            ProcessedWebhookEvent::query()->create([
                'provider' => 'sepay',
                'event_id' => $eventId,
                'checksum' => isset($payload['transaction']['reference_number']) ? (string) $payload['transaction']['reference_number'] : ($payload['referenceCode'] ?? null),
            ]);

            $orderIdOut = $order->id;
        });

        return [
            'processed' => true,
            'order_id' => $orderIdOut,
            'message' => $message,
        ];
    }

    private function findMatchingPendingOrder(int $transferAmount, string $content, mixed $orderIdFromPayload = null): ?Order
    {
        if ($transferAmount < 1) {
            return null;
        }

        // 1. Ưu tiên tìm theo ID trực tiếp từ payload nếu có
        if ($orderIdFromPayload !== null) {
            $id = (int) $orderIdFromPayload;
            $order = Order::query()
                ->where('id', $id)
                ->where('status', OrderStatus::Pending->value)
                ->where('total_cents', $transferAmount)
                ->first();
            
            if ($order) return $order;
            
            // Nếu tìm theo ID không khớp số tiền, ta vẫn tiếp tục tìm theo nội dung
        }

        if ($content === '') {
            return null;
        }

        $needle = mb_strtolower($content);

        // 2. Tìm theo nội dung chuyển khoản (chứa ID hoặc Order Number)
        return Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->where('total_cents', $transferAmount)
            ->orderByDesc('id')
            ->get()
            ->first(function (Order $order) use ($needle): bool {
                $id = (string) $order->id;
                $num = $order->order_number ?? '';

                if (Str::contains($needle, mb_strtolower($id))) {
                    return true;
                }

                if ($num !== '' && Str::contains($needle, mb_strtolower($num))) {
                    return true;
                }

                return false;
            });
    }
}
