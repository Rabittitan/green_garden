<?php

namespace App\Enums;

/** Loại bút toán sổ kho (khớp CHECK chk_sm_type / chk_sm_sign / chk_sm_ref). */
enum StockMovementType: string
{
    case Opening = 'opening';                  // tồn đầu kỳ khi tạo cây: +
    case Sale = 'sale';                        // bán: -
    case SaleCancel = 'sale_cancel';           // hủy đơn trước khi giao, hàng chưa rời kho: +
    case SaleReturn = 'sale_return';           // hàng hoàn về kho: +
    case PurchaseReceipt = 'purchase_receipt'; // nhập từ phiếu nhập: +
    case Adjustment = 'adjustment';            // kiểm kê: + hoặc -
    case Damage = 'damage';                    // cây chết / hỏng / mất: -

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Tồn đầu kỳ',
            self::Sale => 'Bán hàng',
            self::SaleCancel => 'Hủy đơn',
            self::SaleReturn => 'Hàng hoàn về',
            self::PurchaseReceipt => 'Nhập hàng',
            self::Adjustment => 'Kiểm kê',
            self::Damage => 'Hư hỏng',
        };
    }

    /** Dấu bắt buộc của quantity: -1, +1, hoặc 0 = tùy (khác 0). */
    public function sign(): int
    {
        return match ($this) {
            self::Sale, self::Damage => -1,
            self::Opening, self::SaleCancel, self::SaleReturn, self::PurchaseReceipt => 1,
            self::Adjustment => 0,
        };
    }

    public function acceptsQuantity(int $quantity): bool
    {
        return $quantity !== 0 && ($this->sign() === 0 || $this->sign() === ($quantity > 0 ? 1 : -1));
    }

    public function requiresOrderItem(): bool
    {
        return in_array($this, [self::Sale, self::SaleCancel, self::SaleReturn], true);
    }

    public function requiresReceiptItem(): bool
    {
        return $this === self::PurchaseReceipt;
    }
}
