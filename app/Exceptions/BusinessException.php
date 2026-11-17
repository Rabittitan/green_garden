<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lỗi NGHIỆP VỤ: thông điệp an toàn để hiện thẳng cho người dùng (hết hàng, mã không hợp lệ...).
 * Mọi lỗi khác là lỗi hệ thống: ghi log, không lộ chi tiết.
 */
class BusinessException extends RuntimeException {}
