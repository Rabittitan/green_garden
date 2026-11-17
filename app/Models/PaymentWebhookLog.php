<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Nhật ký thô mọi callback từ cổng thanh toán (kể cả sai chữ ký). Chỉ ghi thêm.
 *
 * @property int $id
 * @property string $provider
 * @property string|null $txn_ref
 * @property array<string, mixed> $payload
 * @property bool $signature_valid
 * @property string|null $result_code
 * @property string|null $ip
 * @property Carbon|null $created_at
 */
class PaymentWebhookLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['provider', 'txn_ref', 'payload', 'signature_valid', 'result_code', 'ip'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean'];
    }
}
