<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Sale = 'sale';                       // bán: quantity âm
    case SaleCancel = 'sale_cancel';          // hủy đơn hoàn kho: dương
    case PurchaseReceipt = 'purchase_receipt'; // nhập từ phiếu nhập: dương
    case Adjustment = 'adjustment';           // kiểm kê điều chỉnh: âm hoặc dương
    case Damage = 'damage';                   // cây chết, hỏng: âm
}
