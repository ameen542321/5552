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

    public function test_consistency_check_detects_mismatched_general_and_workflow_statuses(): void
    {
        $order = new \App\Modules\PurchaseOrders\Models\StorePurchaseOrder([
            'status' => 'approved',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);

        $this->assertContains(
            'الحالة العامة لا تطابق المرحلة التشغيلية.',
            \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::consistencyIssues($order)
        );
    }

    public function test_event_help_explains_reversal_without_claiming_the_order_was_deleted(): void
    {
        $help = PurchaseOrderWorkflow::eventHelp('inventory_approval_reversed');

        $this->assertStringContainsString('حركات مقابلة', $help);
        $this->assertStringContainsString('أبقى الطلبية', $help);
        $this->assertStringNotContainsString('حذف', $help);
    }
}
