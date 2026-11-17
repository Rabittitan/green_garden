<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * plants.stock là CACHE của sổ kho: chỉ InventoryService được đổi, bên trong Plant::writingLedger().
 * Mọi đường khác (form admin, tinker, seeder qua Model...) đổi stock sẽ bị ném lỗi ngay,
 * thay vì âm thầm ghi đè làm lệch kho như trước.
 *
 * @property int $id
 * @property int $category_id
 * @property string $sku
 * @property string $name
 * @property string $slug
 * @property int $price
 * @property int $cost_price
 * @property int $stock
 * @property int $reorder_point
 * @property string $light
 * @property string $element
 * @property string|null $image
 * @property string|null $description
 * @property int $rating_sum
 * @property int $rating_count
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Plant extends Model
{
    use SoftDeletes;

    // stock, cost_price, rating_* KHÔNG cho gán hàng loạt.
    protected $fillable = [
        'category_id', 'sku', 'name', 'slug', 'price', 'reorder_point',
        'light', 'element', 'image', 'description', 'is_active',
    ];

    private static int $ledgerDepth = 0;

    /** Chạy $fn trong ngữ cảnh được phép đổi stock / cost_price (chỉ InventoryService dùng). */
    public static function writingLedger(callable $fn): mixed
    {
        self::$ledgerDepth++;
        try {
            return $fn();
        } finally {
            self::$ledgerDepth--;
        }
    }

    protected static function booted(): void
    {
        static::creating(function (Plant $plant) {
            if (self::$ledgerDepth === 0 && (int) $plant->stock !== 0) {
                throw new LogicException('Tạo cây với tồn đầu phải qua InventoryService::opening().');
            }
        });

        static::updating(function (Plant $plant) {
            if (self::$ledgerDepth === 0 && $plant->isDirty(['stock', 'cost_price'])) {
                throw new LogicException('Không được sửa trực tiếp tồn kho / giá vốn. Dùng InventoryService (kiểm kê, nhập hàng).');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'price' => 'integer', 'cost_price' => 'integer', 'stock' => 'integer',
            'reorder_point' => 'integer', 'rating_sum' => 'integer', 'rating_count' => 'integer',
        ];
    }

    const ELEMENTS = ['kim' => 'Kim', 'moc' => 'Mộc', 'thuy' => 'Thủy', 'hoa' => 'Hỏa', 'tho' => 'Thổ'];

    const LIGHTS = ['ua_bong' => 'Ưa bóng', 'ua_sang' => 'Ưa sáng'];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<PlantReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(PlantReview::class);
    }

    /** @return HasMany<PlantReview, $this> */
    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved');
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** @return BelongsToMany<Article, $this> */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getRatingAvgAttribute(): float
    {
        return $this->rating_count ? round($this->rating_sum / $this->rating_count, 1) : 0.0;
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Cây cần nhập thêm: tồn <= ngưỡng riêng của từng cây.
     *
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeNeedsReorder(Builder $q): Builder
    {
        return $q->whereColumn('stock', '<=', 'reorder_point');
    }
}
