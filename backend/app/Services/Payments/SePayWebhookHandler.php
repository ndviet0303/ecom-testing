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
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{processed: bool, order_id: int|null, message: string}
     */
    public function handle(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }

        // Hỗ trợ cả payload phẳng (Bank Webhook) và payload lồng (Gateway IPN)
        $id = $payload['transaction']['id']
            ?? $payload['transaction_id']
            ?? $payload['id']
            ?? null;
        if ($id === null) {
            return ['processed' => false, 'order_id' => null, 'message' => 'Thiếu id giao dịch.'];
        }

        $eventId = 'sepay:' . $id;

        if (ProcessedWebhookEvent::query()->where('provider', 'sepay')->where('event_id', $eventId)->exists()) {
            return ['processed' => true, 'order_id' => null, 'message' => 'Giao dịch webhook đã xử lý.'];
        }

        $transferType = $payload['transaction']['transaction_type'] ?? $payload['transferType'] ?? 'in';
        if (!in_array(strtolower((string) $transferType), ['in', 'payment', 'approved'])) {
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

        $amount = $this->extractAmount($payload);
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
        // 1. Ưu tiên tìm theo ID trực tiếp từ payload nếu có
        if ($orderIdFromPayload !== null) {
            $id = (int) $orderIdFromPayload;
            $order = Order::query()
                ->where('id', $id)
                ->where('status', OrderStatus::Pending->value)
                ->first();

            if ($order && $this->amountMatchesOrder($transferAmount, (int) $order->total_cents, $this->expectedPayableAmount($order))) {
                return $order;
            }

            // Nếu tìm theo ID không khớp số tiền, ta vẫn tiếp tục tìm theo nội dung
        }

        if ($transferAmount < 1) {
            return null;
        }

        if ($content === '') {
            return null;
        }

        // 2. Ưu tiên parse theo đúng format transfer content: <PREFIX><ORDER_ID>
        $parsedOrderId = $this->extractOrderIdFromContent($content);
        if ($parsedOrderId !== null) {
            $order = Order::query()
                ->where('id', $parsedOrderId)
                ->where('status', OrderStatus::Pending->value)
                ->first();

            if ($order && $this->amountMatchesOrder($transferAmount, (int) $order->total_cents, $this->expectedPayableAmount($order))) {
                return $order;
            }
        }

        $needle = mb_strtolower($content);
        $prefix = mb_strtolower(trim((string) config('sepay.transfer_content_prefix', 'TTECOMDZ')));

        // 3. Fallback theo nội dung chuyển khoản (chứa mã tham chiếu đơn)
        return Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->orderByDesc('id')
            ->get()
            ->first(function (Order $order) use ($needle, $transferAmount, $prefix): bool {
                $id = (string) $order->id;
                $num = $order->order_number ?? '';

                if (!$this->amountMatchesOrder($transferAmount, (int) $order->total_cents, $this->expectedPayableAmount($order))) {
                    return false;
                }

                if ($prefix !== '' && Str::contains($needle, $prefix . mb_strtolower($id))) {
                    return true;
                }

                if (strlen($id) >= 4 && Str::contains($needle, mb_strtolower($id))) {
                    return true;
                }

                if ($num !== '' && Str::contains($needle, mb_strtolower($num))) {
                    return true;
                }

                return false;
            });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractAmount(array $payload): int
    {
        $rawAmount = $payload['transaction']['transaction_amount']
            ?? $payload['transferAmount']
            ?? $payload['amountIn']
            ?? $payload['amount']
            ?? $payload['order']['order_amount']
            ?? 0;

        return $this->parseAmountToInt($rawAmount);
    }

    private function amountMatchesOrder(int $incomingAmount, int $orderTotalCents, ?int $expectedPayableAmount = null): bool
    {
        if ($incomingAmount < 1 || $orderTotalCents < 1) {
            return false;
        }

        if ($this->isAmountEquivalent($incomingAmount, $orderTotalCents)) {
            return true;
        }

        if ($expectedPayableAmount !== null && $expectedPayableAmount > 0 && $this->isAmountEquivalent($incomingAmount, $expectedPayableAmount)) {
            return true;
        }

        return false;
    }

    private function isAmountEquivalent(int $incomingAmount, int $expected): bool
    {
        if ($incomingAmount === $expected) {
            return true;
        }

        // Một số payload có thể gửi theo đơn vị lớn (major units), ví dụ 20.00 thay vì 2000.
        return $incomingAmount * 100 === $expected || $incomingAmount === (int) round($expected / 100);
    }

    private function expectedPayableAmount(Order $order): ?int
    {
        $fixed = (int) config('sepay.fixed_qr_amount', 0);

        return $fixed > 0 ? $fixed : null;
    }

    private function extractOrderIdFromContent(string $content): ?int
    {
        $prefix = trim((string) config('sepay.transfer_content_prefix', 'TTECOMDZ'));
        if ($prefix === '') {
            return null;
        }

        $pattern = '/' . preg_quote($prefix, '/') . '\s*([0-9]{1,12})/iu';
        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $id = (int) ($matches[1] ?? 0);

        return $id > 0 ? $id : null;
    }

    private function parseAmountToInt(mixed $amount): int
    {
        if (is_int($amount)) {
            return max(0, $amount);
        }

        if (is_float($amount)) {
            return max(0, (int) round($amount));
        }

        if (!is_string($amount)) {
            return 0;
        }

        $raw = trim($amount);
        if ($raw === '') {
            return 0;
        }

        $normalized = preg_replace('/[^0-9,\.\-]/', '', $raw) ?? '';
        if ($normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === ',') {
            return 0;
        }

        if (preg_match('/^-?\d+$/', $normalized) === 1) {
            return max(0, (int) $normalized);
        }

        $lastDot = strrpos($normalized, '.');
        $lastComma = strrpos($normalized, ',');
        $decimalPos = max($lastDot === false ? -1 : $lastDot, $lastComma === false ? -1 : $lastComma);

        if ($decimalPos === -1) {
            $digits = preg_replace('/\D/', '', $normalized) ?? '';

            return $digits === '' ? 0 : (int) $digits;
        }

        $intPart = preg_replace('/\D/', '', substr($normalized, 0, $decimalPos)) ?? '';
        $decPart = preg_replace('/\D/', '', substr($normalized, $decimalPos + 1)) ?? '';

        if ($intPart === '' && $decPart === '') {
            return 0;
        }

        if (strlen($decPart) === 0) {
            return $intPart === '' ? 0 : (int) $intPart;
        }

        // Quy ước nội bộ đang lưu integer đơn vị nhỏ nhất, nên với số thập phân ta lấy đơn vị lớn.
        return (int) round((float) (($intPart === '' ? '0' : $intPart) . '.' . $decPart));
    }
}
