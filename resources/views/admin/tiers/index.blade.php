@extends('layouts.admin')
@section('title', 'Hạng thành viên')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-crown"></i> Hạng thành viên</h4>
<p class="text-muted small">Hạng xét theo tổng chi tiêu trọn đời (đơn đã hoàn thành và qua {{ config('shop.return_window_days') }} ngày đổi trả, không tính phí ship và phần đã hoàn).
    Hạng chỉ tự động đi lên. Sửa ngưỡng không hạ hạng ai.</p>
<form action="{{ route('admin.tiers.update') }}" method="POST" class="card card-body shadow-sm">@csrf @method('PUT')
    <table class="table align-middle">
        <thead><tr><th>Hạng</th><th>Tên hiển thị</th><th>Tổng chi tiêu từ (đ)</th><th>Giảm %</th><th>Giảm tối đa / đơn (đ)</th><th>Miễn ship từ (đ, trống = mức chung {{ \App\Support\Format::money(config('shop.shipping.free_min_order')) }})</th><th>Số khách</th></tr></thead>
        <tbody>
            @foreach($tiers as $t)
                <tr>
                    <td><code>{{ $t->code }}</code></td>
                    <td><input name="tiers[{{ $t->id }}][name]" value="{{ old("tiers.{$t->id}.name", $t->name) }}" class="form-control form-control-sm"></td>
                    <td><input type="number" name="tiers[{{ $t->id }}][min_spend]" value="{{ old("tiers.{$t->id}.min_spend", $t->min_spend) }}" class="form-control form-control-sm" @readonly($t->rank === 0)></td>
                    <td><input type="number" min="0" max="50" name="tiers[{{ $t->id }}][discount_percent]" value="{{ old("tiers.{$t->id}.discount_percent", $t->discount_percent) }}" class="form-control form-control-sm"></td>
                    <td><input type="number" min="1" name="tiers[{{ $t->id }}][max_discount_per_order]" value="{{ old("tiers.{$t->id}.max_discount_per_order", $t->max_discount_per_order) }}" class="form-control form-control-sm"></td>
                    <td><input type="number" min="0" name="tiers[{{ $t->id }}][free_shipping_min_order]" value="{{ old("tiers.{$t->id}.free_shipping_min_order", $t->free_shipping_min_order) }}" class="form-control form-control-sm"></td>
                    <td>{{ $t->users_count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button class="btn btn-green">Lưu cấu hình</button>
</form>
@endsection
