@extends('layouts.admin')
@section('title', 'Đơn ' . $order->code)

@section('content')
@use('App\Enums\OrderStatus', 'S')
@use('App\Enums\PaymentMethod', 'PM')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<a href="{{ route('admin.orders.index') }}" class="text-green text-decoration-none"><i class="fa fa-arrow-left"></i> Danh sách đơn</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-3">
    <h4 class="fw-bold mb-0">Đơn {{ $order->code }}
        <span class="badge bg-{{ $order->status->color() }} fs-6">{{ $order->status->label() }}</span>
        <span class="badge bg-{{ $order->payment_status->color() }} fs-6">{{ $order->payment_status->label() }}</span>
    </h4>
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted">Đặt lúc @vndt($order->placed_at)</span>
        <a href="{{ route('admin.orders.invoice', $order) }}" class="btn btn-sm btn-outline-secondary text-nowrap" target="_blank" rel="noopener"><i class="fa fa-print"></i> In hóa đơn</a>
    </div>
</div>
{{-- Ghi chú khách gửi shop: để nổi bật, tránh bỏ sót khi soạn / giao hàng --}}
@if($order->note)
    <div class="alert alert-warning d-flex gap-2 align-items-start"><i class="fa fa-note-sticky mt-1"></i>
        <div><strong>Ghi chú của khách:</strong> <span style="white-space: pre-line">{{ $order->note }}</span></div></div>
@endif

<div class="row g-4">
    {{-- ===== CỘT TRÁI: thông tin + thao tác ===== --}}
    <div class="col-lg-4">
        <div class="card card-body shadow-sm mb-3">
            <h6 class="fw-bold">Khách hàng</h6>
            <div>{{ $order->user->name }} · {{ $order->user->email }}
                <span class="badge bg-warning text-dark">{{ $order->user->tier->name }}</span></div>
            <h6 class="fw-bold mt-3">Giao tới</h6>
            <div>{{ $order->recipient_name }} · {{ $order->phone }}</div>
            <div>{{ $order->full_address }}</div>
            <div class="mt-2">Thanh toán: <strong>{{ $order->payment_method->label() }}</strong></div>
            @if($order->status === S::PendingPayment)
                <div class="text-warning">Giữ hàng tới @vndt($order->payment_expires_at)</div>
            @endif
            @if($order->tracking_code)
                <div class="mt-1">Vận chuyển: <strong>{{ $order->shipping_carrier }}</strong> · {{ $order->tracking_code }}</div>
            @endif
            @if($order->cancel_reason) <div class="text-danger mt-1">Lý do hủy: {{ $order->cancel_reason }}</div> @endif
        </div>

        {{-- Thao tác: chỉ hiện bước máy trạng thái cho phép --}}
        <div class="card card-body shadow-sm mb-3">
            <h6 class="fw-bold">Xử lý đơn</h6>
            @if($order->status->isFinal())
                <div class="text-muted">Đơn đã {{ mb_strtolower($order->status->label()) }}, không còn thao tác.</div>
            @endif

            @if($order->status->canTransitionTo(S::Confirmed))
                <form action="{{ route('admin.orders.confirm', $order) }}" method="POST" class="mb-2">@csrf
                    <button class="btn btn-primary w-100"><i class="fa fa-check"></i> Xác nhận đơn</button></form>
            @endif

            @if($order->status->canTransitionTo(S::Shipping))
                <a href="#dong-goi" class="btn btn-info w-100 mb-2"><i class="fa fa-box"></i> Đóng gói & giao (quét mã chậu)</a>
            @endif

            @if($order->status === S::Shipping)
                <form action="{{ route('admin.orders.complete', $order) }}" method="POST" class="mb-2"
                      onsubmit="return confirm('Xác nhận khách đã nhận hàng{{ $order->payment_method === PM::Cod ? ' và shipper đã thu '.\App\Support\Format::money($order->total) : '' }}?')">@csrf
                    <button class="btn btn-success w-100"><i class="fa fa-flag-checkered"></i> Giao thành công
                        {{ $order->payment_method === PM::Cod ? '(đã thu COD)' : '' }}</button></form>
            @endif

            @if($order->status === S::Shipping || ($order->status === S::Completed && $order->isWithinReturnWindow()))
                <form action="{{ route('admin.orders.returning', $order) }}" method="POST" class="mb-2">@csrf
                    <input name="reason" class="form-control mb-1" placeholder="Lý do hoàn (giao thất bại, khách trả...)" required>
                    <button class="btn btn-outline-secondary w-100"><i class="fa fa-rotate-left"></i> Chuyển sang hoàn hàng</button></form>
            @endif

            @if($order->status === S::Returning && $order->completed_at)
                <form action="{{ route('admin.orders.cancel-return', $order) }}" method="POST" class="mb-2">@csrf
                    <input name="note" class="form-control mb-1" placeholder="Ghi chú (khách đổi ý không trả)" required>
                    <button class="btn btn-outline-success w-100"><i class="fa fa-arrow-rotate-left"></i> Hủy yêu cầu trả hàng</button></form>
            @endif

            @if($order->status->canTransitionTo(S::Cancelled))
                <form action="{{ route('admin.orders.cancel', $order) }}" method="POST" onsubmit="return confirm('Hủy đơn {{ $order->code }}? Hàng sẽ trả về kho{{ $order->paid_amount > 0 ? ' và tạo yêu cầu hoàn tiền' : '' }}.')">@csrf
                    <input name="reason" class="form-control mb-1" placeholder="Lý do hủy" required>
                    <button class="btn btn-outline-danger w-100"><i class="fa fa-ban"></i> Hủy đơn</button></form>
            @endif
        </div>

        @if($isAdmin && $order->payment_method === PM::BankTransfer && $order->paid_amount === 0 && in_array($order->status, [S::PendingPayment, S::Cancelled], true))
            <form action="{{ route('admin.orders.confirm-transfer', $order) }}" method="POST" class="card card-body shadow-sm mb-3 border-warning">@csrf
                <h6 class="fw-bold">Xác nhận đã nhận chuyển khoản</h6>
                @if($order->status === S::Cancelled)<div class="small text-danger mb-1">Đơn đã hủy: tiền nhận sau sẽ được tự tạo hoàn trả.</div>@endif
                <input name="amount" type="number" class="form-control mb-1" placeholder="Số tiền nhận được (phải = @money($order->total))" required>
                <input name="bank_ref" class="form-control mb-1" placeholder="Mã giao dịch ngân hàng" required>
                <button class="btn btn-warning w-100">Xác nhận đã nhận tiền</button>
            </form>
        @endif
    </div>

    {{-- ===== CỘT PHẢI: hàng + tiền + lịch sử ===== --}}
    <div class="col-lg-8">
        {{-- Đóng gói: quét mã từng chậu bỏ vào thùng, đủ và đúng cây mới giao được (OrderService::ship kiểm lại) --}}
        @if($order->status->canTransitionTo(S::Shipping))
            @php $packLines = $order->items->map(fn ($i) => ['item' => $i->id, 'plant' => $i->plant_id, 'name' => $i->plant_name, 'qty' => $i->quantity])->values(); @endphp
            <form action="{{ route('admin.orders.ship', $order) }}" method="POST" class="gg-panel gg-panel-body mb-3" id="dong-goi"
                  data-pack data-pack-mode="ship">@csrf
                <h6 class="fw-bold mb-1"><i class="fa fa-box"></i> Đóng gói & giao hàng</h6>
                <p class="small text-muted">Quét tem QR trên <strong>từng chậu</strong> khi bỏ vào thùng. Chỉ nhận chậu đúng cây, còn trong kho; quét đủ mới bấm giao được.</p>
                <script type="application/json" data-pack-lines>@json($packLines)</script>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead><tr><th>Cây</th><th class="text-center">Đã quét</th><th>Mã chậu</th></tr></thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr data-pack-row="{{ $item->id }}"><td>{{ $item->plant_name }}</td>
                                    <td class="text-center text-nowrap"><strong data-pack-count>0</strong> / {{ $item->quantity }}</td>
                                    <td><ul class="gg-scan-list" data-pack-chips></ul></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.scanner', ['label' => 'Quét mã chậu', 'id' => 'pack-scan'])
                <div data-pack-inputs>
                    @foreach(old('unit_codes', []) as $c)<input type="hidden" name="unit_codes[]" value="{{ $c }}">@endforeach
                </div>
                @error('unit_codes') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <div class="row g-2 mt-2">
                    <div class="col-sm-5"><input name="shipping_carrier" class="form-control" placeholder="ĐVVC (GHN, GHTK...)" value="{{ old('shipping_carrier') }}" required aria-label="Đơn vị vận chuyển"></div>
                    <div class="col-sm-4"><input name="tracking_code" class="form-control" placeholder="Mã vận đơn" value="{{ old('tracking_code') }}" required aria-label="Mã vận đơn"></div>
                    <div class="col-sm-3"><button class="btn btn-info w-100" data-pack-submit><i class="fa fa-truck"></i> Giao cho vận chuyển</button></div>
                </div>
            </form>
        @endif

        <div class="table-responsive">
        <table class="table bg-white shadow-sm align-middle">
            <thead class="table-success"><tr><th>Cây</th><th>Đơn giá</th><th>SL</th><th>Giảm hạng</th><th>Giảm mã</th><th class="text-end">Thành tiền</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->plant_name }} <div class="small text-muted">{{ $item->plant_sku }} · giá vốn @money($item->unit_cost)</div>
                            @if($item->returned_good_qty + $item->returned_damaged_qty > 0)
                                <div class="small">Hoàn về: {{ $item->returned_good_qty }} tốt, {{ $item->returned_damaged_qty }} hỏng</div>
                            @endif
                            @if($item->units->isNotEmpty())
                                <div class="small mt-1" data-testid="item-units">Chậu: @foreach($item->units as $u)<a href="{{ route('admin.units.index', ['ma' => $u->code]) }}" class="gg-unit-chip {{ $u->status->value }}" title="{{ $u->status->label() }}">{{ $u->displayCode() }}</a> @endforeach</div>
                            @endif</td>
                        <td>@money($item->unit_price)</td>
                        <td>{{ $item->quantity }}</td>
                        <td class="text-danger">{{ $item->member_discount ? '−'.\App\Support\Format::money($item->member_discount) : '' }}</td>
                        <td class="text-danger">{{ $item->coupon_discount ? '−'.\App\Support\Format::money($item->coupon_discount) : '' }}</td>
                        <td class="text-end">@money($item->line_total)</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="5" class="text-end">Tạm tính</td><td class="text-end">@money($order->subtotal)</td></tr>
                @if($order->member_discount)<tr class="text-danger"><td colspan="5" class="text-end">Ưu đãi hạng {{ $order->tier->name }} ({{ $order->member_discount_percent }}%)</td><td class="text-end">−@money($order->member_discount)</td></tr>@endif
                @if($order->coupon_discount)<tr class="text-danger"><td colspan="5" class="text-end">Mã {{ $order->coupon_code }}</td><td class="text-end">−@money($order->coupon_discount)</td></tr>@endif
                <tr><td colspan="5" class="text-end">Phí ship</td><td class="text-end">@money($order->shipping_fee)</td></tr>
                @if($order->shipping_discount)<tr class="text-danger"><td colspan="5" class="text-end">Giảm ship ({{ $order->coupon_code }})</td><td class="text-end">−@money($order->shipping_discount)</td></tr>@endif
                <tr><th colspan="5" class="text-end">Tổng</th><th class="text-end text-green fs-5">@money($order->total)</th></tr>
                <tr><td colspan="5" class="text-end">Đã thu / Đã hoàn</td><td class="text-end">@money($order->paid_amount) / @money($order->refunded_amount)</td></tr>
            </tfoot>
        </table>
        </div>

        {{-- Nhận hàng hoàn --}}
        @if($order->status === S::Returning)
            @php $packLines = $order->items->map(fn ($i) => ['item' => $i->id, 'plant' => $i->plant_id, 'name' => $i->plant_name, 'qty' => $i->quantity,
                'codes' => $i->units->where('status', \App\Enums\PlantUnitStatus::Sold)->pluck('code')->values()])->values(); @endphp
            <form action="{{ route('admin.orders.receive-return', $order) }}" method="POST" class="card card-body shadow-sm mb-3 border-secondary"
                  data-pack data-pack-mode="damaged">@csrf
                <h6 class="fw-bold">Nhận hàng hoàn về kho</h6>
                <p class="small text-muted mb-2">Chậu tốt tự về kho. Chậu <strong>hỏng / chết</strong>: quét tem của chậu đó, hệ thống tự tính số hỏng (chậu hỏng được xuất hủy).</p>
                <script type="application/json" data-pack-lines>@json($packLines)</script>
                <input type="hidden" name="scanned" value="0" data-pack-scanned>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Cây</th><th>Số lượng</th><th>Tốt (nhập lại kho)</th><th>Hỏng / mất</th></tr></thead>
                    @foreach($order->items as $item)
                        <tr data-pack-row="{{ $item->id }}"><td>{{ $item->plant_name }}<ul class="gg-scan-list mt-1" data-pack-chips></ul></td><td>{{ $item->quantity }}</td>
                            <td><input type="number" min="0" max="{{ $item->quantity }}" name="items[{{ $item->id }}][good]" value="{{ old("items.{$item->id}.good", $item->quantity) }}" class="form-control form-control-sm" data-pack-good></td>
                            <td><input type="number" min="0" max="{{ $item->quantity }}" name="items[{{ $item->id }}][damaged]" value="{{ old("items.{$item->id}.damaged", 0) }}" class="form-control form-control-sm" data-pack-damaged></td></tr>
                    @endforeach
                </table>
                @include('admin.partials.scanner', ['label' => 'Quét tem chậu HỎNG (chậu tốt không cần quét)', 'id' => 'return-scan'])
                <div data-pack-inputs></div>
                @if($refundable > 0)
                    <label class="form-label">Số tiền hoàn cho khách (tối đa @money($refundable))</label>
                    <input type="number" name="refund_amount" min="1" max="{{ $refundable }}" value="{{ old('refund_amount', $refundable) }}" class="form-control mb-2">
                @endif
                <input name="note" class="form-control mb-2" placeholder="Ghi chú (vd: lỗi do khách, trừ phí ship)">
                <button class="btn btn-secondary">Xác nhận đã nhận hàng hoàn</button>
            </form>
        @endif

        {{-- Thanh toán --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Giao dịch thanh toán</div>
            <table class="table mb-0 align-middle small">
                <thead><tr><th>Mã GD</th><th>Loại</th><th>Số tiền</th><th>Trạng thái</th><th>Mã cổng / NH</th><th>Thời gian</th><th></th></tr></thead>
                <tbody>
                @forelse($order->payments as $p)
                    <tr>
                        <td><code>{{ $p->txn_ref }}</code></td>
                        <td>{{ $p->type->label() }} ({{ $p->method->value }})</td>
                        <td>@money($p->amount)</td>
                        <td>{{ $p->status->label() }}</td>
                        <td>{{ $p->provider_txn_no }}</td>
                        <td>@vndt($p->paid_at ?? $p->created_at)</td>
                        <td>
                            @if($isAdmin && $p->type === \App\Enums\PaymentType::Refund && $p->status === \App\Enums\PaymentState::Pending)
                                <form action="{{ route('admin.payments.confirm-refund', $p) }}" method="POST" class="d-flex gap-1">@csrf
                                    <input name="provider_ref" class="form-control form-control-sm" placeholder="Mã GD hoàn" required>
                                    <button class="btn btn-sm btn-warning text-nowrap">Đã hoàn</button></form>
                            @endif
                        </td>
                    </tr>
                    @if($p->note)<tr><td colspan="7" class="text-muted">↳ {{ $p->note }}</td></tr>@endif
                @empty
                    <tr><td colspan="7" class="text-muted text-center">Không có giao dịch (đơn 0đ).</td></tr>
                @endforelse
                </tbody>
            </table>
            @if($isAdmin && $refundable > 0 && $order->status !== S::Returning)
                <form action="{{ route('admin.orders.refunds.store', $order) }}" method="POST" class="card-body border-top d-flex gap-2">@csrf
                    <input type="number" name="amount" min="1" max="{{ $refundable }}" class="form-control" placeholder="Số tiền hoàn (tối đa @money($refundable))" required>
                    <input name="note" class="form-control" placeholder="Lý do (vd: cây chết sau khi nhận)" required>
                    <button class="btn btn-outline-warning text-nowrap">Tạo hoàn tiền</button>
                </form>
            @endif
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Lịch sử trạng thái</div>
            <ul class="list-group list-group-flush small">
                @foreach($order->histories as $h)
                    <li class="list-group-item">@vndt($h->created_at) — {{ $h->from_status?->label() ?? 'Tạo đơn' }} → <strong>{{ $h->to_status->label() }}</strong>
                        · {{ $h->actor_type->label() }}{{ $h->changedBy ? ': '.$h->changedBy->name : '' }}
                        @if($h->note) <span class="text-muted">({{ $h->note }})</span> @endif</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/packing.js') }}?v={{ @filemtime(public_path('js/packing.js')) }}"></script>
@endpush
