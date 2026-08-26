<?php

namespace Tests\Unit;

use App\Models\Accountant;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use App\Modules\PurchaseOrders\Services\StorePurchaseOrderService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorePurchaseOrderInventoryReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_order_submit_requires_available_stock_without_exposing_quantity(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $product = $this->product($owner, $store, ['quantity' => 2]);
        $service = new StorePurchaseOrderService();

        $order = $service->createOrderForAccountant($store, $accountant, [
            'supplier_name' => 'مورد الاختبار',
            'items' => [
                ['product_id' => $product->id, 'quantity_requested' => 5, 'unit_type' => 'unit'],
            ],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('بعض الكميات تحتاج مراجعة');

        $service->submitForOwnerReview($order, $accountant);
    }

    public function test_accountant_inventory_submission_saves_snapshot_without_changing_stock(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $product = $this->product($owner, $store, ['quantity' => 12]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => StorePurchaseOrderService::STATUS_INVENTORY_RETURNED,
            'returned_for_inventory_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 3,
            'unit_type' => 'unit',
            'cost_price_at_order' => 10,
        ]);

        $submitted = (new StorePurchaseOrderService())->submitInventoryReview($order, $accountant, [
            $item->id => [
                'inventory_counted_quantity' => 11,
                'inventory_count_notes' => 'تم الجرد',
            ],
        ]);

        $this->assertSame(StorePurchaseOrderService::STATUS_INVENTORY_SUBMITTED, $submitted->status);
        $this->assertSame(12.0, (float) $product->fresh()->quantity);
        $this->assertDatabaseHas('store_purchase_order_items', [
            'id' => $item->id,
            'inventory_counted_quantity' => 11,
            'inventory_snapshot_quantity' => 12,
            'inventory_count_notes' => 'تم الجرد',
        ]);
        $this->assertDatabaseHas('notifications', [
            'target_type' => 'user',
            'title' => 'طلبية تمت مراجعتها',
        ]);
    }

    public function test_owner_can_return_reviewed_order_for_inventory_and_reject_with_notes(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => StorePurchaseOrderService::STATUS_PENDING_OWNER_REVIEW,
            'sent_at' => now(),
        ]);

        $service = new StorePurchaseOrderService();
        $returned = $service->returnForInventory($order, $owner, 'أدخل الجرد الفعلي');

        $this->assertSame(StorePurchaseOrderService::STATUS_INVENTORY_RETURNED, $returned->status);
        $this->assertSame('أدخل الجرد الفعلي', $returned->owner_notes);
        $this->assertDatabaseHas('notifications', [
            'target_type' => 'accountants',
            'title' => 'طلب جرد للطلبية',
        ]);

        $returned->update(['status' => StorePurchaseOrderService::STATUS_INVENTORY_SUBMITTED]);
        $rejected = $service->rejectForAccountant($returned->fresh(), $owner, 'غير مناسبة');

        $this->assertSame(StorePurchaseOrderService::STATUS_REJECTED, $rejected->status);
        $this->assertSame('غير مناسبة', $rejected->owner_notes);
    }

    private function ownerStoreAndAccountant(): array
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $accountant = Accountant::create([
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'name' => 'محاسب الاختبار',
            'email' => 'accountant-' . uniqid() . '@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        return [$owner, $store, $accountant];
    }

    private function product(User $owner, Store $store, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج اختبار',
            'price' => 20,
            'cost_price' => 10,
            'quantity' => 10,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ], $overrides));
    }
}
