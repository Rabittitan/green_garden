<?php

namespace App\Services;

use App\Models\Plant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Danh mục cây: thêm (kèm tồn đầu kỳ qua sổ kho), sửa thông tin, ngừng bán + xóa mềm.
 * Tồn kho / giá vốn KHÔNG sửa ở đây: chỉ đổi qua InventoryService (sổ kho).
 */
class PlantService
{
    public function __construct(private InventoryService $inventory) {}

    /** @param array<string, mixed> $fields */
    public function create(array $fields, int $openingStock, int $costPrice, bool $active, ?int $by): Plant
    {
        return DB::transaction(function () use ($fields, $openingStock, $costPrice, $active, $by) {
            $sku = ($fields['sku'] ?? null) ?: null;
            $plant = Plant::create(['sku' => $sku ?? 'TMP-'.Str::uuid(), 'slug' => $this->uniqueSlug((string) $fields['name']), 'is_active' => $active] + $fields);
            if ($sku === null) {                                                // mã tự sinh theo id: GL00017
                $plant->update(['sku' => 'GL'.str_pad((string) $plant->id, 5, '0', STR_PAD_LEFT)]);
            }
            $this->inventory->opening($plant, $openingStock, $costPrice, $by);

            return $plant;
        }, 3);
    }

    /** @param array<string, mixed> $fields */
    public function update(Plant $plant, array $fields): Plant
    {
        return DB::transaction(function () use ($plant, $fields) {
            $plant->update($fields);                                        // stock / cost_price không có trong $fields (và bị chặn ở model)

            return $plant;
        }, 3);
    }

    /** Ngừng bán rồi xóa mềm: đơn cũ, sổ kho, đánh giá vẫn giữ nguyên tham chiếu. */
    public function delete(Plant $plant): void
    {
        DB::transaction(function () use ($plant) {
            $plant->update(['is_active' => false]);
            $plant->delete();
        }, 3);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'cay';
        $slug = $base;
        for ($i = 2; Plant::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
