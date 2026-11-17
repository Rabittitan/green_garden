<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Plant;
use App\Services\CartService;
use Illuminate\Http\Request;

/** Giỏ hàng (session). Giá hiển thị luôn lấy từ DB. */
class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index()
    {
        foreach ($this->cart->reconcile() as $notice) {
            session()->now('warning', $notice);
        }
        $items = $this->cart->items();
        $plants = Plant::whereIn('id', array_keys($items))->get()->keyBy('id');
        $lines = collect($items)->map(fn ($qty, $id) => ['plant' => $plants[$id], 'quantity' => $qty, 'total' => $plants[$id]->price * $qty]);

        return view('cart.index', ['lines' => $lines, 'subtotal' => $lines->sum('total')]);
    }

    public function add(Request $request, int $id)
    {
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:999']]);
        $plant = Plant::findOrFail($id);
        try {
            $this->cart->add($plant, (int) ($data['quantity'] ?? 1));
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã thêm \"{$plant->name}\" vào giỏ hàng!");
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:0', 'max:999'], 'action' => ['nullable', 'in:increase,decrease']]);
        $plant = Plant::withTrashed()->findOrFail($id);
        $current = $this->cart->items()[$id] ?? 0;
        $qty = match ($data['action'] ?? null) {
            'increase' => $current + 1,
            'decrease' => $current - 1,
            default => (int) ($data['quantity'] ?? $current),
        };
        try {
            $this->cart->setQuantity($plant, $qty);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    public function remove(int $id)
    {
        $this->cart->remove($id);

        return back()->with('success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
    }
}
