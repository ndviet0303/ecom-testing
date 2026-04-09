<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Thông tin tích hợp SePay (QR ảnh + webhook IPN).
 */
class SePayPlaceholderController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $hasQr = filled(config('sepay.account_number')) && filled(config('sepay.bank_code'));

        return response()->json([
            'provider' => 'sepay',
            'qr_image_pattern' => config('sepay.qr_image_base_url').'?acc=...&bank=...&amount=...&des=...',
            'webhook_post_url' => url('/api/v1/webhooks/sepay'),
            'order_qr_get' => '/api/v1/orders/{order}/sepay-qr',
            'checkout_field' => 'payment_method: sepay_qr',
            'configured_for_qr' => $hasQr,
            'message' => $hasQr
                ? 'Đã cấu hình TK/bank cho QR. Đặt SEPAY_WEBHOOK_API_KEY và URL webhook trên my.sepay.vn.'
                : 'Thiết lập SEPAY_ACCOUNT_NUMBER và SEPAY_BANK_CODE trong .env.',
        ]);
    }
}
