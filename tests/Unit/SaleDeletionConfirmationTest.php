<?php

namespace Tests\Unit;

use App\Support\SaleDeletionConfirmation;
use PHPUnit\Framework\TestCase;

class SaleDeletionConfirmationTest extends TestCase
{
    public function test_plain_sale_only_asks_for_confirmation(): void
    {
        $this->assertSame(
            'هل أنت متأكد من حذف العملية رقم #5211؟',
            SaleDeletionConfirmation::message(5211, false, false, false, false)
        );
    }

    public function test_only_relevant_deletion_effects_are_included(): void
    {
        $creditMessage = SaleDeletionConfirmation::message(10, true, true, false, false);
        $this->assertStringContainsString('الأجل المرتبط', $creditMessage);
        $this->assertStringContainsString('التحصيلات المرتبطة', $creditMessage);
        $this->assertStringNotContainsString('المخزون', $creditMessage);
        $this->assertStringNotContainsString('شغل اليد', $creditMessage);

        $stockAndLaborMessage = SaleDeletionConfirmation::message(11, false, false, true, true);
        $this->assertStringContainsString('المخزون', $stockAndLaborMessage);
        $this->assertStringContainsString('شغل اليد', $stockAndLaborMessage);
        $this->assertStringNotContainsString('الأجل', $stockAndLaborMessage);
        $this->assertStringNotContainsString('التحصيلات', $stockAndLaborMessage);
    }

    public function test_credit_without_collections_does_not_claim_collections_exist(): void
    {
        $message = SaleDeletionConfirmation::message(12, true, false, false, false);

        $this->assertStringContainsString('الأجل المرتبط', $message);
        $this->assertStringNotContainsString('التحصيلات المرتبطة', $message);
    }
}
