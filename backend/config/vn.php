<?php

/**
 * Thị trường Việt Nam — API ecom chỉ phục vụ nội địa VN.
 *
 * Các cột *cents trong DB lưu số nguyên theo đơn vị VND (không có phần thập phân).
 */
return [

    'country_code' => env('ECM_VN_COUNTRY', 'VN'),

    'currency_code' => env('ECM_VN_CURRENCY', 'VND'),

    /** VAT mặc định (basis points): 10% = 1000 — client gửi tax_rate_basis_points khi checkout. */
    'default_vat_basis_points' => (int) env('ECM_VN_DEFAULT_VAT_BPS', 1000),

];
