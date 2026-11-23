<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\HomeSlide;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Slide trang chủ: thêm / sửa / xóa; ảnh tải lên lưu ở storage/app/public/slides (dọn file cũ khi thay / xóa). */
class HomeSlideService
{
    public const IMAGE_DIR = 'slides';

    public function __construct(private ImageStorage $images) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?UploadedFile $image): HomeSlide
    {
        $stored = $image ? $this->images->store($image, self::IMAGE_DIR) : null;
        try {
            return DB::transaction(function () use ($data, $stored) {
                // Giới hạn mềm cho giao diện quản trị (không phải ràng buộc tiền/kho): 2 người thêm cùng lúc
                // có thể vượt 1 slide, trang chủ vẫn chỉ hiện tối đa HomeSlide::MAX.
                if (HomeSlide::count() >= HomeSlide::MAX) {
                    throw new BusinessException('Tối đa '.HomeSlide::MAX.' slide. Hãy xóa bớt slide cũ trước khi thêm.');
                }
                $url = $stored ?? $this->externalUrl($data['image_url'] ?? null)
                    ?? throw new BusinessException('Cần tải ảnh lên hoặc dán link ảnh https://.');

                return HomeSlide::create($this->attributes($data) + ['image' => $url]);
            }, 3);
        } catch (\Throwable $e) {
            $this->images->delete($stored, self::IMAGE_DIR);

            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(HomeSlide $slide, array $data, ?UploadedFile $image): HomeSlide
    {
        $stored = $image ? $this->images->store($image, self::IMAGE_DIR) : null;
        $old = $slide->image;
        try {
            $slide->fill($this->attributes($data));
            $slide->image = $stored ?? $this->externalUrl($data['image_url'] ?? null) ?? $old;
            $slide->save();
        } catch (\Throwable $e) {
            $this->images->delete($stored, self::IMAGE_DIR);

            throw $e;
        }
        if ($old !== $slide->image) {
            $this->images->delete($old, self::IMAGE_DIR);
        }

        return $slide;
    }

    public function delete(HomeSlide $slide): void
    {
        $image = $slide->image;
        $slide->delete();
        $this->images->delete($image, self::IMAGE_DIR);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'title' => $data['title'] ?? null, 'subtitle' => $data['subtitle'] ?? null,
            'link_url' => $data['link_url'] ?? null, 'button_label' => $data['button_label'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0), 'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function externalUrl(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https://#i', $url) ? $url : null;
    }
}
