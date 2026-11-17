<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plant extends Model
{
    use SoftDeletes;

    // rating_sum, rating_count KHÔNG cho gán hàng loạt: chỉ ReviewService thay đổi.
    // 'stock' vẫn giữ để form admin và seeder hiện tại chạy được; mọi thay đổi stock
    // không đi qua InventoryService sẽ được booted() tự ghi thành bút toán 'adjustment'.
    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'price', 'cost_price', 'stock', 'reorder_point',
        'light', 'element', 'image', 'description', 'is_active',
    ];

    /** InventoryService bật cờ này khi nó tự ghi sổ kho, để không ghi trùng. */
    public static bool $ledgerHandled = false;

    protected static function booted(): void
    {
        static::created(function (Plant $plant) {
            if (! static::$ledgerHandled && $plant->stock > 0) {
                $plant->logAdjustment($plant->stock, 'Tồn đầu khi tạo cây');
            }
        });

        static::updated(function (Plant $plant) {
            if (! static::$ledgerHandled && $plant->wasChanged('stock')) {
                $delta = $plant->stock - $plant->getOriginal('stock');
                $plant->logAdjustment($delta, 'Sửa tồn kho trực tiếp');
            }
        });
    }

    private function logAdjustment(int $delta, string $note): void
    {
        if ($delta === 0) {
            return;
        }
        StockMovement::create([
            'plant_id' => $this->id, 'type' => 'adjustment', 'quantity' => $delta,
            'stock_after' => $this->stock, 'unit_cost' => $this->cost_price ?: null,
            'created_by' => auth()->id(), 'note' => $note,
        ]);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    const ELEMENTS = ['kim' => 'Kim', 'moc' => 'Mộc', 'thuy' => 'Thủy', 'hoa' => 'Hỏa', 'tho' => 'Thổ'];
    const LIGHTS = ['ua_bong' => 'Ưa bóng', 'ua_sang' => 'Ưa sáng'];

    public function category()      { return $this->belongsTo(Category::class); }
    public function orderItems()    { return $this->hasMany(OrderItem::class); }
    public function reviews()       { return $this->hasMany(PlantReview::class); }
    public function approvedReviews() { return $this->reviews()->where('status', 'approved'); }
    public function comments()      { return $this->morphMany(Comment::class, 'commentable'); }
    public function articles()      { return $this->belongsToMany(Article::class); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }

    /** Điểm trung bình, tính từ tổng để không bị lệch do làm tròn. */
    public function getRatingAvgAttribute(): float
    {
        return $this->rating_count ? round($this->rating_sum / $this->rating_count, 1) : 0.0;
    }

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    /** Cây cần nhập thêm: tồn <= ngưỡng riêng của từng cây. */
    public function scopeNeedsReorder(Builder $q): Builder
    {
        return $q->whereColumn('stock', '<=', 'reorder_point');
    }
}
