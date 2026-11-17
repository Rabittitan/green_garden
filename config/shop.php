<?php

/*
 * Tham số nghiệp vụ của GreenLife Shop. Đổi chính sách ở đây, không sửa code.
 */
return [

    // Múi giờ HIỂN THỊ và cắt ngày báo cáo. DB vẫn lưu theo APP timezone (UTC) để không lệch dữ liệu cũ.
    'display_timezone' => 'Asia/Ho_Chi_Minh',

    'order_code_prefix' => 'GL',

    'shipping' => [
        'base_fee' => (int) env('SHOP_SHIPPING_FEE', 30000),
        // Miễn phí ship khi (tiền hàng sau giảm) >= ngưỡng. Hạng thành viên có thể có ngưỡng riêng thấp hơn.
        'free_min_order' => (int) env('SHOP_FREE_SHIPPING_MIN', 500000),
    ],

    'payment' => [
        // Giữ hàng chờ thanh toán (phút)
        'hold_minutes' => [
            'vnpay' => 15,
            'bank_transfer' => 24 * 60,
        ],
        // Chờ thêm sau hạn trước khi hủy, phòng IPN đến chậm vài giây
        'expire_grace_minutes' => 2,
        // Mỗi khách giữ tối đa bao nhiêu đơn chờ thanh toán cùng lúc (chống giữ hết hàng)
        'max_pending_orders_per_user' => 3,
        // Cổng VNPay: 'fake' (dev, tự giả lập) | 'vnpay' (sandbox/thật)
        'gateway' => env('PAYMENT_GATEWAY', 'fake'),
    ],

    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'version' => '2.1.0',
    ],

    'bank_transfer' => [
        'bank_name' => env('SHOP_BANK_NAME', 'Vietcombank'),
        'account_no' => env('SHOP_BANK_ACCOUNT_NO', ''),
        'account_name' => env('SHOP_BANK_ACCOUNT_NAME', 'GREENLIFE SHOP'),
    ],

    // Khách được trả hàng trong N ngày sau khi nhận. Hết hạn này đơn mới được cộng vào tổng chi tiêu xét hạng.
    'return_window_days' => 7,
];
