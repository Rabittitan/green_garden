<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sổ địa chỉ. default_key là cột sinh (UNIQUE) đảm bảo mỗi khách 1 địa chỉ mặc định: KHÔNG được ghi. */
class UserAddress extends Model
{
    protected $fillable = ['user_id', 'recipient_name', 'phone', 'province', 'district', 'ward', 'address_line', 'is_default'];

    protected $hidden = ['default_key'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', [$this->address_line, $this->ward, $this->district, $this->province]);
    }

    /** Dữ liệu snapshot để chép sang đơn hàng. */
    public function toShippingSnapshot(): array
    {
        return $this->only(['recipient_name', 'phone', 'province', 'district', 'ward', 'address_line']);
    }
}
