<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/** Nhà cung cấp: thêm / sửa (không xóa: phiếu nhập cũ còn tham chiếu; ngừng hợp tác thì tắt is_active). */
class SupplierService
{
    /** @param array<string, mixed> $fields */
    public function create(array $fields): Supplier
    {
        return DB::transaction(fn () => Supplier::create($fields + ['is_active' => true]), 3);
    }

    /** @param array<string, mixed> $fields */
    public function update(Supplier $supplier, array $fields, bool $active): Supplier
    {
        return DB::transaction(function () use ($supplier, $fields, $active) {
            $supplier->update($fields + ['is_active' => $active]);

            return $supplier;
        }, 3);
    }
}
