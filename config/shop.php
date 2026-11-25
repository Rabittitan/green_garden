<?php

/*
 * Tham số nghiệp vụ của GreenGarden Shop. Đổi chính sách ở đây, không sửa code.
 */
return [

    // Múi giờ HIỂN THỊ và cắt ngày báo cáo. DB vẫn lưu theo APP timezone (UTC) để không lệch dữ liệu cũ.
    'display_timezone' => 'Asia/Ho_Chi_Minh',

    'order_code_prefix' => 'GL',

    // Proxy tin cậy (nginx/Cloudflare/load balancer) để lấy đúng IP khách + nhận ra HTTPS.
    // VD: "10.0.0.0/8,172.16.0.0/12" hoặc "*" (chỉ khi app KHÔNG nhận kết nối trực tiếp từ internet). Trống = không tin proxy nào.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'shipping' => [
        'base_fee' => (int) env('SHOP_SHIPPING_FEE', 30000),
        // Miễn phí ship khi (tiền hàng sau giảm) >= ngưỡng. Hạng thành viên có thể có ngưỡng riêng thấp hơn.
        'free_min_order' => (int) env('SHOP_FREE_SHIPPING_MIN', 500000),
        // Khối lượng đóng gói mặc định của 1 cây khi chưa nhập (gram) — dùng cho phí ship theo vùng + cân nặng
        'default_weight_grams' => 1000,
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
        'account_name' => env('SHOP_BANK_ACCOUNT_NAME', 'GREENGARDEN SHOP'),
    ],

    // Trang chủ: mục "Sản phẩm hot" = cây bán chạy nhất trong N ngày (đơn đã đặt, chưa hủy / hoàn)
    'home' => [
        'hot_days' => 30,
        'hot_limit' => 8,
    ],

    // Thông tin người bán in trên hóa đơn bán lẻ (trang "In hóa đơn" của đơn hàng). Đây KHÔNG phải hóa đơn GTGT.
    'seller' => [
        'name' => env('SHOP_SELLER_NAME', 'GreenGarden Shop'),
        'address' => env('SHOP_SELLER_ADDRESS', ''),
        'phone' => env('SHOP_SELLER_PHONE', ''),
        'email' => env('SHOP_SELLER_EMAIL', ''),
        'tax_code' => env('SHOP_SELLER_TAX_CODE', ''),
    ],

    // Địa chỉ nhận hàng trả về (hiện trong email + trang đơn khi khách yêu cầu trả hàng)
    'return_address' => env('SHOP_RETURN_ADDRESS', 'GreenGarden Shop - 12 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh - 0900 000 001'),

    // Khách được trả hàng trong N ngày sau khi nhận. Hết hạn này đơn mới được cộng vào tổng chi tiêu xét hạng.
    'return_window_days' => 7,
];
