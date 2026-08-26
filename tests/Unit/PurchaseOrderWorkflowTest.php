<?php

namespace Tests\Unit;

use App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow;
use PHPUnit\Framework\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    public function test_known_status_has_one_arabic_label_for_all_purchase_order_views(): void
    {
        $this->assertSame(
            'بانتظار مراجعة تأكيد الاستلام',
            PurchaseOrderWorkflow::label('pending_owner_receipt_review')
        );
    }

    public function test_unknown_status_never_exposes_its_database_value(): void
    {
        $this->assertSame(
            PurchaseOrderWorkflow::UNKNOWN_LABEL,
            PurchaseOrderWorkflow::label('unexpected_internal_status')
        );
        $this->assertStringNotContainsString('unexpected_internal_status', PurchaseOrderWorkflow::label('unexpected_internal_status'));
    }

    public function test_support_options_and_transitions_are_derived_from_the_same_catalog(): void
    {
        $this->assertSame(
            array_keys(PurchaseOrderWorkflow::supportTransitions()),
            array_keys(PurchaseOrderWorkflow::supportLabels())
        );
        $this->assertArrayNotHasKey('approved_and_supplied', PurchaseOrderWorkflow::supportTransitions());
    }

    public function test_each_pdf_type_is_limited_to_its_workflow_stage(): void
    {
        $this->assertTrue(PurchaseOrderWorkflow::allowsPdf('order', 'draft'));
        $this->assertFalse(PurchaseOrderWorkflow::allowsPdf('order', 'received'));
        $this->assertTrue(PurchaseOrderWorkflow::allowsPdf('receipt', 'sent'));
        $this->assertTrue(PurchaseOrderWorkflow::allowsPdf('receipt', 'approved'));
        $this->assertFalse(PurchaseOrderWorkflow::allowsPdf('inventory', 'received'));
        $this->assertTrue(PurchaseOrderWorkflow::allowsPdf('inventory', 'approved'));
        $this->assertTrue(PurchaseOrderWorkflow::allowsPdf('inventory-count', 'draft', 'count_draft'));
        $this->assertFalse(PurchaseOrderWorkflow::allowsPdf('inventory-count', 'draft', 'approved'));
        $this->assertFalse(PurchaseOrderWorkflow::allowsPdf('unexpected', 'approved'));
    }
}
