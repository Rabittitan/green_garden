<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    // Xem giỏ hàng
    public function index()
    {
        $cart = session('cart', []);              // lấy giỏ từ session, chưa có thì là mảng rỗng
        $total = 0;                               // tổng tiền ban đầu bằng 0
        foreach ($cart as $item) {                // duyệt từng món trong giỏ
            $total += $item['price'] * $item['quantity']; // cộng dồn: giá x số lượng
        }
        return view('cart.index', compact('cart', 'total')); // gửi sang view giỏ hàng
    }

    // Thêm cây vào giỏ
    public function add(Request $request, $id)
    {
        $plant = Plant::findOrFail($id);                       // tìm cây trong DB, không có thì 404
        $cart = session('cart', []);                           // lấy giỏ hiện tại
        $qty = max(1, (int) $request->input('quantity', 1));   // số lượng muốn thêm, tối thiểu là 1
        $current = isset($cart[$id]) ? $cart[$id]['quantity'] : 0; // số lượng cây này đang có sẵn trong giỏ

        if ($current + $qty > $plant->stock) {                 // nếu tổng vượt quá tồn kho
            return back()->with('error', "Chỉ còn {$plant->stock} cây \"{$plant->name}\" trong kho!"); // báo lỗi và quay lại
        }

        if (isset($cart[$id])) {                               // nếu cây đã có trong giỏ
            $cart[$id]['quantity'] += $qty;                    // thì cộng thêm số lượng
        } else {                                               // nếu chưa có
            $cart[$id] = ['name' => $plant->name, 'price' => $plant->price, 'image' => $plant->image, 'quantity' => $qty]; // thêm mới
        }

        session(['cart' => $cart]);                            // lưu giỏ ngược lại vào session
        return back()->with('success', "Đã thêm \"{$plant->name}\" vào giỏ hàng!"); // quay lại kèm thông báo
    }

    // Tăng / giảm số lượng (nút + và - trong giỏ)
    public function update(Request $request, $id)
    {
        $cart = session('cart', []);                           // lấy giỏ hiện tại
        if (isset($cart[$id])) {                               // chỉ xử lý nếu cây có trong giỏ
            if ($request->action === 'increase') {             // bấm nút +
                $stock = Plant::find($id)->stock;              // lấy tồn kho thật từ DB
                if ($cart[$id]['quantity'] < $stock) {         // còn hàng thì mới cho tăng
                    $cart[$id]['quantity']++;                  // tăng 1
                } else {
                    return back()->with('error', 'Đã đạt số lượng tối đa trong kho!'); // hết hàng thì báo lỗi
                }
            } else {                                           // bấm nút -
                $cart[$id]['quantity']--;                      // giảm 1
                if ($cart[$id]['quantity'] <= 0) {             // nếu giảm về 0
                    unset($cart[$id]);                         // thì xóa luôn khỏi giỏ
                }
            }
        }
        session(['cart' => $cart]);                            // lưu lại giỏ
        return back();                                         // quay lại trang giỏ hàng
    }

    // Xóa 1 cây khỏi giỏ
    public function remove($id)
    {
        $cart = session('cart', []);                           // lấy giỏ hiện tại
        unset($cart[$id]);                                     // xóa cây có key = id
        session(['cart' => $cart]);                            // lưu lại giỏ
        return back()->with('success', 'Đã xóa sản phẩm khỏi giỏ hàng.'); // quay lại kèm thông báo
    }
}