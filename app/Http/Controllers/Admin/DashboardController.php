<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plant;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $completed = Order::status(OrderStatus::Completed);

        return view('admin.dashboard', [
            // Doanh thu hàng hóa: tiền hàng sau mọi giảm giá của đơn đã hoàn thành (không gồm phí ship)
            'revenue' => (int) (clone $completed)->sum(DB::raw('subtotal - member_discount - coupon_discount')),
            'refunded' => (int) Order::sum('refunded_amount'),
            // Tiền thực thu = đã thu - đã hoàn (mọi đơn)
            'cashIn' => (int) Order::sum(DB::raw('CAST(paid_amount AS SIGNED) - CAST(refunded_amount AS SIGNED)')),
            'cogs' => (int) OrderItem::whereHas('order', fn ($q) => $q->where('status', OrderStatus::Completed->value))
                ->sum(DB::raw('unit_cost * quantity')),
            'counts' => Order::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'needRefund' => Order::where('payment_status', PaymentStatus::RefundPending->value)->count(),
            'topPlants' => OrderItem::select('plant_id', 'plant_name', DB::raw('SUM(quantity) as sold'))
                ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Completed->value))
                ->groupBy('plant_id', 'plant_name')->orderByDesc('sold')->take(5)->get(),
            'lowStock' => Plant::active()->needsReorder()->orderBy('stock')->take(10)->get(),
            'latestOrders' => Order::latest('id')->take(8)->get(),
        ]);
    }
}
