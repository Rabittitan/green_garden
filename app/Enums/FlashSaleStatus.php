<?php

namespace App\Enums;

/** Vòng đời đợt flash sale: nhân viên soạn -> gửi duyệt -> quản trị viên duyệt / từ chối; đã duyệt thì chỉ quản trị viên hủy. */
enum FlashSaleStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Submitted => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Bị từ chối',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Submitted => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'dark',
        };
    }

    /** Nhân viên còn sửa được nội dung (giờ, cây, giá). */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }
}
