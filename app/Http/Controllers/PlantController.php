<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Plant;
use Illuminate\Http\Request;

class PlantController extends Controller
{
    // Danh sách cây + bộ lọc (danh mục, giá, ánh sáng, mệnh, từ khóa)
    public function index(Request $request)
    {
        // Bắt đầu câu truy vấn, nạp sẵn danh mục
        $query = Plant::with('category')->active();             // chỉ cây đang bán

        // Lọc theo danh mục (nếu URL có ?category=2)
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Lọc theo đặc tính ánh sáng (?light=ua_bong)
        if ($request->filled('light')) {
            $query->where('light', $request->light);
        }

        // Lọc theo mệnh phong thủy (?element=kim)
        if ($request->filled('element')) {
            $query->where('element', $request->element);
        }

        // Lọc theo từ khóa tên cây (?keyword=kim)
        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%' . $request->keyword . '%');
        }

        // Lọc theo khoảng giá (?price=100-300)
        if ($request->filled('price')) {
            switch ($request->price) {
                case 'duoi-100':                                   // dưới 100.000đ
                    $query->where('price', '<', 100000);
                    break;
                case '100-300':                                    // từ 100.000 đến 300.000đ
                    $query->whereBetween('price', [100000, 300000]);
                    break;
                case '300-500':                                    // từ 300.000 đến 500.000đ
                    $query->whereBetween('price', [300000, 500000]);
                    break;
                case 'tren-500':                                   // trên 500.000đ
                    $query->where('price', '>', 500000);
                    break;
            }
        }

        // Lấy kết quả, mỗi trang 9 cây; withQueryString giữ lại bộ lọc khi bấm sang trang 2, 3...
        $plants = $query->latest()->paginate(9)->withQueryString();

        // Lấy danh mục để vẽ ô lọc bên trái
        $categories = Category::active()->orderBy('sort_order')->get();

        // Gửi sang view
        return view('plants.index', compact('plants', 'categories'));
    }

    // Chi tiết 1 cây
    public function show($id)
    {
        // Tìm cây theo id, không thấy thì tự trả về trang 404
        $plant = Plant::with('category')->active()->findOrFail($id);   // cây ngừng bán -> 404

        // Lấy 4 cây cùng mệnh (trừ chính nó) để gợi ý "Cây cùng mệnh"
        $related = Plant::active()->where('element', $plant->element)
            ->where('id', '!=', $plant->id)
            ->take(4)->get();

        return view('plants.show', compact('plant', 'related'));
    }
}