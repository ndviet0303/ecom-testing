<?php

namespace App\Services\Payments;

use App\Models\Order;

class SePayQrService
{
    /**
     * Nội dung chuyển khoản khách phải ghi (và SePay gửi lại trong webhook.content).
     */
    public function transferContent(Order $order): string
    {
        $prefix = trim((string) config('sepay.transfer_content_prefix', 'TTECOMDZ'));
        $num = (string) $order->id;

        return trim($prefix . '' . $num);
    }

    /**
     * URL ảnh QR (redirect hoặc <img src="...">).
     *
     * @see https://qr.sepay.vn/img?acc=...&bank=...&amount=...&des=...
     */
    public function qrImageUrl(Order $order, ?int $amount = null): string
    {
        $acc = config('sepay.account_number');
        $bank = config('sepay.bank_code');

        if ($acc === null || $acc === '' || $bank === null || $bank === '') {
            throw new \RuntimeException('Chưa cấu hình SEPAY_ACCOUNT_NUMBER hoặc SEPAY_BANK_CODE.');
        }

        $base = rtrim((string) config('sepay.qr_image_base_url', 'https://qr.sepay.vn/img'), '/');

        $query = http_build_query([
            'acc' => $acc,
            'bank' => $bank,
            'amount' => max(0, (int) ($amount ?? $order->total_cents)),
            'des' => $this->transferContent($order),
        ], '', '&', PHP_QUERY_RFC3986);

        return $base . '?' . $query;
    }
}
