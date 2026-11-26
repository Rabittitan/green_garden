{{-- Biến: $sale (FlashSale), $plants, $chosen (plant_id => FlashSaleItem). Giờ nhập theo giờ Việt Nam. --}}
@php $tz = config('shop.display_timezone'); @endphp
<div class="gg-panel gg-panel-body mb-3">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold" for="name">Tên đợt sale *</label>
            <input id="name" name="name" maxlength="150" required value="{{ old('name', $sale->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="VD: Flash sale cuối tuần">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold" for="starts_at">Bắt đầu *</label>
            <input type="datetime-local" id="starts_at" name="starts_at" required value="{{ old('starts_at', $sale->starts_at?->copy()->setTimezone($tz)->format('Y-m-d\TH:i')) }}" class="form-control @error('starts_at') is-invalid @enderror">
            @error('starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold" for="ends_at">Kết thúc *</label>
            <input type="datetime-local" id="ends_at" name="ends_at" required value="{{ old('ends_at', $sale->ends_at?->copy()->setTimezone($tz)->format('Y-m-d\TH:i')) }}" class="form-control @error('ends_at') is-invalid @enderror">
            @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>
<div class="gg-panel">
    <div class="gg-panel-body pb-0"><div class="fw-semibold">Cây trong đợt sale</div>
        <p class="small text-muted mb-2">Nhập <strong>giá sale</strong> cho cây muốn đưa vào (để trống = không sale). Số suất để trống = không giới hạn (vẫn không quá tồn kho).</p></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="small text-muted"><tr><th class="ps-3">Cây</th><th class="text-end">Giá bán</th><th class="text-end">Giá vốn</th><th class="text-end">Tồn</th><th style="width:160px">Giá sale (đ)</th><th style="width:130px">Số suất</th></tr></thead>
            <tbody>
                @foreach($plants as $p)
                    @php $c = $chosen[$p->id] ?? null; @endphp
                    <tr>
                        <td class="ps-3">{{ $p->name }}<div class="small text-muted">{{ $p->sku }}</div></td>
                        <td class="text-end">@money($p->price)</td>
                        <td class="text-end small text-muted">@money($p->cost_price)</td>
                        <td class="text-end">{{ $p->stock }}</td>
                        <td><input type="number" min="1" max="{{ $p->price - 1 }}" name="items[{{ $p->id }}][sale_price]" value="{{ old("items.{$p->id}.sale_price", $c?->sale_price) }}" class="form-control form-control-sm text-end" aria-label="Giá sale {{ $p->name }}"></td>
                        <td><input type="number" min="1" name="items[{{ $p->id }}][quantity_limit]" value="{{ old("items.{$p->id}.quantity_limit", $c?->quantity_limit) }}" class="form-control form-control-sm text-end" aria-label="Số suất {{ $p->name }}"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<button class="btn btn-green mt-3"><i class="fa fa-floppy-disk"></i> Lưu nháp</button>
<a href="{{ route('admin.flash-sales.index') }}" class="btn btn-outline-secondary mt-3">Hủy</a>
