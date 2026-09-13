<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use App\Modules\PurchaseOrders\Services\StorePurchaseOrderService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reversal_removes_supplied_stock_and_preserves_the_order_audit_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'user']);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج اختبار العكس',
            'slug' => 'reversal-product-'.$store->id,
            'price' => 20,
            'cost_price' => 12,
            'quantity' => 8,
            'min_stock' => 1,
            'status' => 'active',
            'product_type' => 'standard',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'approved',
            'workflow_status' => 'approved_and_supplied',
            'approved_at' => now(),
            'approved_business_date' => now()->toDateString(),
            'approval_operation_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 2,
            'quantity_received' => 2,
            'unit_type' => 'unit',
            'cost_price_at_order' => 20,
            'cost_price_at_receipt' => 20,
            'stock_quantity_before' => 6,
            'stock_quantity_after' => 8,
            'cost_price_before' => 10,
            'cost_price_after' => 12,
        ]);

        app(StorePurchaseOrderService::class)->reverseApproval(
            $order,
            $admin,
            'اعتماد مكرر ثبت بعد مراجعة تذكرة الدعم',
            now()->toDateString()
        );

        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 6, 'cost_price' => 10]);
        $this->assertDatabaseHas('store_purchase_orders', ['id' => $order->id, 'workflow_status' => 'reversed', 'reversed_by' => $admin->id]);
        $this->assertDatabaseHas('store_purchase_order_events', ['store_purchase_order_id' => $order->id, 'event' => 'inventory_approval_reversed']);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'decrease']);
    }
}
