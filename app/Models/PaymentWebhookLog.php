<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nhật ký thô mọi callback từ cổng thanh toán (kể cả sai chữ ký). Chỉ ghi thêm. */
class PaymentWebhookLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['provider', 'txn_ref', 'payload', 'signature_valid', 'result_code', 'ip'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean'];
    }
}
