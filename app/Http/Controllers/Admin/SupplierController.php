<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Nhà cung cấp. Không xóa (phiếu nhập cũ vẫn tham chiếu): chỉ ngừng hợp tác. */
class SupplierController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function rules(?Supplier $s = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('suppliers', 'code')->ignore($s?->id)],
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function index(): View
    {
        return view('admin.suppliers.index', ['suppliers' => Supplier::withCount('goodsReceipts')->orderByDesc('is_active')->orderBy('name')->paginate(20)]);
    }

    public function store(Request $request, SupplierService $suppliers): RedirectResponse
    {
        $suppliers->create($request->validate($this->rules()));

        return back()->with('success', 'Đã thêm nhà cung cấp.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier, SupplierService $suppliers): RedirectResponse
    {
        $suppliers->update($supplier, $request->validate($this->rules($supplier)), $request->boolean('is_active'));

        return redirect()->route('admin.suppliers.index')->with('success', 'Đã cập nhật nhà cung cấp.');
    }
}
