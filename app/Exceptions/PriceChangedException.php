<?php

namespace App\Exceptions;

use App\Services\Pricing\Quote;
use App\Support\Format;

/** Giá / ưu đãi đã đổi giữa lúc khách xem và lúc đặt: không tạo đơn, cho khách xem lại báo giá mới. */
class PriceChangedException extends BusinessException
{
    public function __construct(public readonly Quote $quote)
    {
        parent::__construct('Giá hoặc ưu đãi vừa thay đổi. Tổng mới là '.Format::money($quote->total()).', vui lòng kiểm tra lại trước khi đặt.');
    }
}
