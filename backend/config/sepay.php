<?php

/**
 * SePay — QR VietQR + Webhook IPN (tiền vào).
 *
 * @see https://docs.sepay.vn/tich-hop-webhooks.html
 * QR ảnh: https://qr.sepay.vn/img?acc=...&bank=...&amount=...&des=...
 */
return [

    'qr_image_base_url' => env('SEPAY_QR_IMAGE_BASE_URL', 'https://qr.sepay.vn/img'),

    /** Số TK nhận (hiển thị trong QR) */
    'account_number' => env('SEPAY_ACCOUNT_NUMBER'),

    /** Mã ngân hàng SePay/VietQR, ví dụ TPBank */
    'bank_code' => env('SEPAY_BANK_CODE', 'TPBank'),

    'transfer_content_prefix' => env('SEPAY_TRANSFER_CONTENT_PREFIX', 'TTECOMDZ'),

    /**
     * Số tiền cố định khi tạo QR để test nhanh (VD 2000).
     * Để null/rỗng để dùng đúng total đơn hàng.
     */
    'fixed_qr_amount' => env('SEPAY_FIXED_QR_AMOUNT', 2000),

    /** Secret Key — đặt trong SePay dashboard (loại xác thực SECRET_KEY) */
    'secret_key' => env('SEPAY_SECRET_KEY'),

    /** Header Authorization: Apikey <key> — đặt trong SePay khi tạo webhook (loại xác thực API_KEY) */
    'webhook_api_key' => env('SEPAY_WEBHOOK_API_KEY'),

    /**
     * Nếu set: chỉ chấp nhận webhook khi accountNumber khớp (sau khi bỏ khoảng trắng).
     * Nên bật trên production.
     */
    'verify_account_number' => (bool) env('SEPAY_VERIFY_ACCOUNT_NUMBER', true),
];
