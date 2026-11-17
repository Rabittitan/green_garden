<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Plant;
use Illuminate\Contracts\Session\Session;

/**
 * Giỏ hàng trong session: CHỈ lưu plant_id => số lượng. Giá luôn đọc từ DB khi hiển thị / đặt hàng.
 * Đọc được cả định dạng cũ (['name','price','quantity',...]) để khách đang có giỏ lúc nâng cấp không bị mất.
 */
class CartService
{
    private const KEY = 'cart';

    public function __construct(private Session $session) {}

    /** @return array<int,int> */
    public function items(): array
    {
        $items = [];
        foreach ((array) $this->session->get(self::KEY, []) as $id => $value) {
            $qty = is_array($value) ? (int) ($value['quantity'] ?? 0) : (int) $value;
            if ((int) $id > 0 && $qty > 0) {
                $items[(int) $id] = $qty;
            }
        }

        return $items;
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    public function add(Plant $plant, int $qty): void
    {
        $this->assertSellable($plant);
        $items = $this->items();
        $new = ($items[$plant->id] ?? 0) + max(1, $qty);
        if ($new > $plant->stock) {
            throw new BusinessException("Chỉ còn {$plant->stock} cây \"{$plant->name}\" trong kho.");
        }
        $items[$plant->id] = $new;
        $this->save($items);
    }

    public function setQuantity(Plant $plant, int $qty): void
    {
        $items = $this->items();
        if ($qty <= 0) {
            unset($items[$plant->id]);
        } else {
            $this->assertSellable($plant);
            if ($qty > $plant->stock) {
                throw new BusinessException("Chỉ còn {$plant->stock} cây \"{$plant->name}\" trong kho.");
            }
            $items[$plant->id] = $qty;
        }
        $this->save($items);
    }

    public function remove(int $plantId): void
    {
        $items = $this->items();
        unset($items[$plantId]);
        $this->save($items);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * Đối chiếu giỏ với kho hiện tại: bỏ cây ngừng bán, giảm số lượng vượt tồn. Trả danh sách thông báo.
     *
     * @return list<string>
     */
    public function reconcile(): array
    {
        $items = $this->items();
        $plants = Plant::whereIn('id', array_keys($items))->get()->keyBy('id');
        $notices = [];
        foreach ($items as $id => $qty) {
            $plant = $plants->get($id);
            if (! $plant || ! $plant->is_active) {
                unset($items[$id]);
                $notices[] = 'Một cây trong giỏ đã ngừng bán và đã được bỏ ra khỏi giỏ.';
            } elseif ($plant->stock === 0) {
                unset($items[$id]);
                $notices[] = "Cây \"{$plant->name}\" vừa hết hàng và đã được bỏ ra khỏi giỏ.";
            } elseif ($qty > $plant->stock) {
                $items[$id] = $plant->stock;
                $notices[] = "Cây \"{$plant->name}\" chỉ còn {$plant->stock} cây, số lượng trong giỏ đã được điều chỉnh.";
            }
        }
        $this->save($items);

        return $notices;
    }

    private function assertSellable(Plant $plant): void
    {
        if (! $plant->is_active || $plant->trashed()) {
            throw new BusinessException("Cây \"{$plant->name}\" đã ngừng bán.");
        }
    }

    /**
     * @param  array<int, int>  $items
     */
    private function save(array $items): void
    {
        $this->session->put(self::KEY, $items);
    }
}
