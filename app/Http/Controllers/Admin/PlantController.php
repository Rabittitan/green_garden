<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlantController extends Controller
{
    // Quy tắc kiểm tra dữ liệu dùng chung cho thêm & sửa
    private function rules()
    {
        return [
            'category_id' => 'required|exists:categories,id',           // danh mục phải tồn tại
            'name'        => 'required|max:150',                        // tên bắt buộc
            'price'       => 'required|integer|min:0',                  // giá là số nguyên >= 0
            'stock'       => 'required|integer|min:0',                  // tồn kho là số nguyên >= 0
            'light'       => ['required', Rule::in(array_keys(Plant::LIGHTS))],   // chỉ nhận ua_bong | ua_sang
            'element'     => ['required', Rule::in(array_keys(Plant::ELEMENTS))], // chỉ nhận kim|moc|thuy|hoa|tho
            'image'       => 'nullable|url|max:500',                    // link ảnh (có thể bỏ trống)
            'description' => 'nullable',                                // mô tả (có thể bỏ trống)
        ];
    }

    // Danh sách cây
    public function index()
    {
        $plants = Plant::with('category')->latest()->paginate(10);      // 10 cây/trang, mới nhất trước
        return view('admin.plants.index', compact('plants'));
    }

    // Form thêm mới
    public function create()
    {
        $categories = Category::all();                                  // để vẽ ô chọn danh mục
        return view('admin.plants.create', compact('categories'));
    }

    // Lưu cây mới
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());                     // kiểm tra, sai thì tự quay lại kèm lỗi
        Plant::create($data);                                           // thêm vào DB
        return redirect()->route('admin.plants.index')->with('success', 'Đã thêm cây mới!');
    }

    // Form sửa
    public function edit($id)
    {
        $plant = Plant::findOrFail($id);                                // tìm cây cần sửa
        $categories = Category::all();                                  // danh mục cho ô chọn
        return view('admin.plants.edit', compact('plant', 'categories'));
    }

    // Lưu thay đổi
    public function update(Request $request, $id)
    {
        $plant = Plant::findOrFail($id);                                // tìm cây cần sửa
        $data = $request->validate($this->rules());                     // kiểm tra dữ liệu
        $plant->update($data);                                          // cập nhật vào DB
        return redirect()->route('admin.plants.index')->with('success', 'Đã cập nhật cây!');
    }

    // Xóa cây
    public function destroy($id)
    {
        $plant = Plant::findOrFail($id);                                // tìm cây cần xóa
        if ($plant->orderItems()->exists()) {                           // nếu cây này đã từng được bán trong đơn nào đó
            return back()->with('error', 'Cây này đã có trong đơn hàng, không thể xóa! Hãy đặt tồn kho = 0 thay thế.'); // chặn xóa để không hỏng lịch sử đơn
        }
        $plant->delete();                                               // chưa ai mua thì xóa được
        return back()->with('success', 'Đã xóa cây!');
    }
}