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

    /** Nội thành (miễn phí ship): so khớp theo tên tỉnh/thành trong address.province. */
    'inner_city_province' => env('ECM_VN_INNER_CITY_PROVINCE', 'Thành phố Hà Nội'),

    /** Danh sách quận nội thành Hà Nội được miễn phí ship. */
    'inner_city_districts' => array_values(array_filter(array_map(
        static fn(string $v): string => trim($v),
        explode(',', (string) env('ECM_VN_INNER_CITY_DISTRICTS', 'Ba Đình,Hoàn Kiếm,Đống Đa,Hai Bà Trưng,Hoàng Mai,Thanh Xuân,Cầu Giấy,Tây Hồ,Long Biên,Hà Đông,Nam Từ Liêm,Bắc Từ Liêm'))
    ))),

    /** Phí ship mặc định cho ngoại thành/liên tỉnh. */
    'default_shipping_cents' => (int) env('ECM_VN_DEFAULT_SHIPPING_CENTS', 30_000),

];
