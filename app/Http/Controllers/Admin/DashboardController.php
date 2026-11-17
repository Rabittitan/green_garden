<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plant;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // Trang thống kê
    public function index()
    {
        // Tổng doanh thu = cộng tổng tiền của các đơn ĐÃ HOÀN THÀNH (đơn hủy/đang xử lý chưa tính)
        $totalRevenue = Order::where('status', 'completed')->sum('total');

        // Tổng số đơn hàng (tất cả trạng thái)
        $totalOrders = Order::count();

        // Số đơn đang chờ xử lý (để admin biết còn việc cần làm)
        $pendingOrders = Order::where('status', 'pending')->count();

        // Tổng số loại cây đang bán
        $totalPlants = Plant::count();

        // Top 5 cây bán chạy: gom nhóm theo cây, cộng tổng số lượng bán, chỉ tính đơn hoàn thành
        $topPlants = OrderItem::select('plant_id', DB::raw('SUM(quantity) as sold')) // chọn id cây và tổng số lượng bán (đặt tên là sold)
            ->whereHas('order', function ($q) {                // chỉ lấy dòng thuộc đơn
                $q->where('status', 'completed');              // đã hoàn thành
            })
            ->groupBy('plant_id')                              // gom nhóm theo từng cây
            ->orderByDesc('sold')                              // bán nhiều nhất lên đầu
            ->with('plant')                                    // nạp thông tin cây để hiện tên, ảnh
            ->take(5)                                          // lấy 5 cây
            ->get();

        // Cây sắp hết hàng (tồn kho <= 5) để nhắc admin nhập thêm
        $lowStock = Plant::where('stock', '<=', 5)->orderBy('stock')->get();

        // 5 đơn mới nhất
        $latestOrders = Order::latest()->take(5)->get();

        return view('admin.dashboard', compact('totalRevenue', 'totalOrders', 'pendingOrders', 'totalPlants', 'topPlants', 'lowStock', 'latestOrders'));
    }
}