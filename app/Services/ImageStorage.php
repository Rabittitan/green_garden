<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lưu ảnh shop tải lên (tin tức, slide trang chủ) vào disk "public": storage/app/public/<thư mục>/Năm/Tháng/<uuid>.<đuôi>.
 * Trả về đường dẫn TƯƠNG ĐỐI "/storage/…" (không phụ thuộc APP_URL). Tên file do server đặt; đuôi theo MIME thật của file.
 * Kiểm tra kích thước / loại ở FormRequest (rule IMAGE_RULES); ở đây chặn thêm lần nữa theo MIME.
 */
class ImageStorage
{
    /** JPG/PNG/WEBP/GIF (không nhận SVG: có thể chứa mã), tối đa 4MB, cạnh dài tối đa 6000px. */
    public const RULES = ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096', 'dimensions:max_width=6000,max_height=6000'];

    public function store(UploadedFile $file, string $dir): string
    {
        $ext = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
            default => throw new BusinessException('Chỉ nhận ảnh JPG, PNG, WEBP hoặc GIF.'),
        };
        $path = $file->storeAs($dir.'/'.now()->format('Y/m'), Str::uuid().'.'.$ext, 'public');
        if ($path === false) {
            throw new BusinessException('Không lưu được ảnh, vui lòng thử lại.');
        }

        return '/storage/'.$path;
    }

    /** Xóa file do chính shop tải lên trong thư mục $dir (link ngoài https://… thì bỏ qua). */
    public function delete(?string $publicPath, string $dir): void
    {
        $prefix = '/storage/'.$dir.'/';
        if ($publicPath !== null && str_starts_with($publicPath, $prefix) && ! str_contains($publicPath, '..')) {
            Storage::disk('public')->delete(substr($publicPath, strlen('/storage/')));
        }
    }
}
