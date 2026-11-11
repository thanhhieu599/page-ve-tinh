<?php

/*
 * Thông tin D&T dùng chung cho mọi site (schema Organization, footer, trang giới thiệu).
 * Một nơi duy nhất: đổi ở đây là mọi site đổi theo. Mục để trống thì không hiển thị.
 */
return [
    'name' => env('COMPANY_NAME', 'D&T'),
    'legal_name' => env('COMPANY_LEGAL_NAME', 'Công ty TNHH Thương mại Dịch vụ Phát triển Kỹ thuật D & T'),
    'url' => env('COMPANY_URL', 'https://trangthietbi.vn'),
    // Đường dẫn tuyệt đối tới logo (https), dùng cho schema Organization.
    'logo' => env('COMPANY_LOGO_URL'),
    'tax_id' => env('COMPANY_TAX_ID'),
    'phone' => env('COMPANY_PHONE'),
    'email' => env('COMPANY_EMAIL'),
    'street_address' => env('COMPANY_STREET_ADDRESS'),
    'locality' => env('COMPANY_LOCALITY'),
    'region' => env('COMPANY_REGION'),
    // Hình thức thanh toán mặc định ở footer (sửa trong trang quản trị → Thông tin công ty).
    'payment_methods' => ['Chuyển khoản ngân hàng', 'Tiền mặt khi nhận hàng', 'Công nợ cho doanh nghiệp (theo hợp đồng)'],
];
