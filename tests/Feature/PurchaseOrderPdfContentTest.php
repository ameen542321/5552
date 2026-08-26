<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderPdfContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_template_renders_short_medium_and_long_orders_without_inline_styles(): void
    {
        foreach ([1, 20, 100] as $itemCount) {
            [$store, $order] = $this->orderWithItems($itemCount);
            $html = view('modules.purchase-orders.pdf', [
                'store' => $store,
                'order' => $order,
                'totalCost' => $itemCount * 10,
                'customNotes' => [],
                'isReceiptPdf' => false,
                'isInventoryApprovalPdf' => false,
                'isReceiptWorksheet' => false,
                'hidePrices' => false,
            ])->render();

            $this->assertStringNotContainsString('style="', $html);
            $this->assertGreaterThanOrEqual($itemCount, substr_count($html, '<tr>'));
            $this->assertStringContainsString('طلبية توريد', $html);
        }
    }

    public function test_hidden_price_pdf_does_not_render_cost_columns_or_total(): void
    {
        [$store, $order] = $this->orderWithItems(1);
        $html = view('modules.purchase-orders.pdf', [
            'store' => $store,
            'order' => $order,
            'totalCost' => 99999,
            'customNotes' => [],
            'isReceiptPdf' => false,
            'isInventoryApprovalPdf' => false,
            'isReceiptWorksheet' => false,
            'hidePrices' => true,
        ])->render();

        $this->assertStringNotContainsString('تكلفة الطلب', $html);
        $this->assertStringNotContainsString('إجمالي التكلفة التقديرية', $html);
        $this->assertStringNotContainsString('99,999.00', $html);
    }

    private function orderWithItems(int $itemCount): array
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد اختبار PDF',
            'status' => 'draft',
            'workflow_status' => 'pending_owner_review',
        ]);

        foreach (range(1, $itemCount) as $index) {
            StorePurchaseOrderItem::create([
                'store_purchase_order_id' => $order->id,
                'custom_product_name' => 'منتج PDF طويل '.$index,
                'quantity_requested' => 1,
                'unit_type' => 'piece',
                'cost_price_at_order' => 10,
                'add_to_owner_purchases' => true,
            ]);
        }

        $order->load(['items.product', 'items.matchedProduct', 'store.user', 'accountant']);
        $store->load(['user', 'accountants']);

        return [$store, $order];
    }
}
