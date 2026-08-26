<?php

namespace Tests\Unit;

use App\Support\PaymentTypeLabel;
use PHPUnit\Framework\TestCase;

class PaymentTypeLabelTest extends TestCase
{
    public function test_daily_sales_labels_internal_use_as_internal_consumption(): void
    {
        $this->assertSame('استهلاك', PaymentTypeLabel::dailySalesLabel('internal_use'));
    }

    public function test_daily_sales_credit_labels_remain_unchanged(): void
    {
        $this->assertSame('آجل', PaymentTypeLabel::dailySalesLabel('credit', 100));
        $this->assertSame('تم التحصيل', PaymentTypeLabel::dailySalesLabel('credit', 0));
    }
}
