<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\HomeSlide;
use App\Services\HomeSlideService;
use App\Services\ImageStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Slide ảnh đầu trang chủ (nhân viên + quản trị): thêm, sửa, bật/tắt, sắp thứ tự, xóa. */
class HomeSlideController extends Controller
{
    public function __construct(private HomeSlideService $slides) {}

    public function index(): View
    {
        return view('admin.slides.index', ['slides' => HomeSlide::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function create(): View
    {
        $next = (int) HomeSlide::max('sort_order') + 1;

        return view('admin.slides.create', ['slide' => new HomeSlide(['is_active' => true, 'sort_order' => $next])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        try {
            $this->slides->create($data, $request->file('image'));
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.slides.index')->with('success', 'Đã thêm slide.');
    }

    public function edit(HomeSlide $slide): View
    {
        return view('admin.slides.edit', compact('slide'));
    }

    public function update(Request $request, HomeSlide $slide): RedirectResponse
    {
        $data = $this->validated($request, false);
        try {
            $this->slides->update($slide, $data, $request->file('image'));
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.slides.index')->with('success', 'Đã cập nhật slide.');
    }

    public function destroy(HomeSlide $slide): RedirectResponse
    {
        $this->slides->delete($slide);

        return redirect()->route('admin.slides.index')->with('success', 'Đã xóa slide.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            // Ảnh: bắt buộc khi thêm (tải lên hoặc link https); khi sửa để trống = giữ ảnh cũ
            'image' => [$creating ? 'required_without:image_url' : 'nullable', ...ImageStorage::RULES],
            'image_url' => ['nullable', 'url:https', 'max:255'],
            // Link khi bấm: đường dẫn trong site ("/cay-canh?element=kim") hoặc https://…
            'link_url' => ['nullable', 'string', 'max:255', 'regex:#^(/(?!/)|https://)#i'],
            'button_label' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ], [
            'link_url.regex' => 'Link phải là đường dẫn trong shop (bắt đầu bằng /) hoặc https://…',
            'image.required_without' => 'Cần tải ảnh lên hoặc dán link ảnh https://.',
        ], ['title' => 'tiêu đề', 'subtitle' => 'dòng phụ', 'image' => 'ảnh', 'image_url' => 'link ảnh',
            'link_url' => 'link', 'button_label' => 'chữ trên nút', 'sort_order' => 'thứ tự']);
        unset($data['image']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
