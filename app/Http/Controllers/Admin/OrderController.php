<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class OrderController extends Controller
{
    // Danh sách đơn hàng (có lọc theo trạng thái ?status=pending)
    public function index(Request $request)
    {
        $query = Order::with('user');                                   // nạp kèm thông tin khách

        if ($request->filled('status')) {                               // nếu admin chọn lọc trạng thái
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(10)->withQueryString();    // mới nhất trước, 10 đơn/trang
        return view('admin.orders.index', compact('orders'));
    }

    // Chi tiết 1 đơn
    public function show($id)
    {
        $order = Order::with(['user', 'items.plant'])->findOrFail($id); // nạp khách + chi tiết + cây
        return view('admin.orders.show', compact('order'));
    }

    // Đổi trạng thái đơn (kể cả HỦY ĐƠN -> hoàn kho + trả lượt mã giảm giá)
    public function updateStatus(Request $request, $id, OrderService $orders)
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note'   => 'nullable|max:255',
        ]);

        try {
            $order = $orders->changeStatus(Order::findOrFail($id), OrderStatus::from($data['status']), $request->user(), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã cập nhật trạng thái đơn #'.$order->id.' thành "'.Order::STATUSES[$order->status].'".');
    }
}