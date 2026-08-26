<?php

namespace Tests\Feature;

use App\Models\Accountant;
use App\Models\Employee;
use App\Models\Product;
use App\Models\InventoryLog;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreTransfer;
use App\Models\StoreTransferItem;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class HistoricalProductSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_item_keeps_original_product_identity_and_values_after_catalog_changes(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $employee = Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'Historical sale employee',
            'phone' => '0500000021',
            'salary' => 1000,
            'status' => 'active',
        ]);
        $accountant = Accountant::create([
            'employee_id' => $employee->id,
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'name' => 'Historical sale accountant',
            'email' => 'historical-sale-accountant@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'لمبة سكن 5 وات',
            'slug' => 'lamp-5w-old',
            'price' => 50,
            'cost_price' => 30,
            'quantity' => 20,
            'status' => 'active',
            'product_type' => 'standard',
            'usage_type' => Product::USAGE_TYPE_SALE,
            'roll_length' => 0,
            'is_splittable' => true,
            'items_per_unit' => 10,
            'piece_price' => 5,
            'min_stock' => 1,
        ]);
        $sale = Sale::create([
            'store_id' => $store->id,
            'accountant_id' => $accountant->id,
            'sale_type' => 'cash',
            'products_total' => 50,
            'final_total' => 50,
            'paid_amount' => 50,
            'total' => 50,
        ]);

        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_type' => 'unit',
            'price' => 50,
            'cost_price' => 30,
            'total' => 50,
        ]);

        $product->update([
            'name' => 'لمبة سكن 5 وات - نوع جديد',
            'price' => 7,
            'cost_price' => 4,
            'is_splittable' => false,
            'items_per_unit' => 1,
        ]);
        $product->delete();

        $item = $item->fresh();
        $this->assertSame('لمبة سكن 5 وات', $item->historical_product_name);
        $this->assertSame('طقم', $item->historical_unit_label);
        $this->assertSame(50.0, $item->sale_price_snapshot);
        $this->assertSame(30.0, $item->cost_price_snapshot);
        $this->assertSame(10.0, $item->items_per_unit_snapshot);
        $this->assertSame('captured', $item->snapshot_source);
    }

    public function test_inventory_history_survives_physical_product_purge(): void
    {
        $owner = User::factory()->create();
        $senderStore = Store::factory()->create(['user_id' => $owner->id]);
        $receiverStore = Store::factory()->create(['user_id' => $owner->id]);
        $product = Product::create([
            'store_id' => $senderStore->id,
            'user_id' => $owner->id,
            'name' => 'منتج تاريخي',
            'slug' => 'historical-inventory-product',
            'price' => 25,
            'cost_price' => 15,
            'quantity' => 8,
            'status' => 'active',
            'product_type' => 'standard',
            'usage_type' => Product::USAGE_TYPE_SALE,
            'roll_length' => 0,
            'is_splittable' => false,
            'items_per_unit' => 1,
            'piece_price' => 0,
            'min_stock' => 1,
        ]);

        $movement = StockMovement::create([
            'store_id' => $senderStore->id,
            'product_id' => $product->id,
            'user_id' => $owner->id,
            'type' => 'decrease',
            'quantity' => 2,
            'balance_before' => 10,
            'balance_after' => 8,
        ]);
        $inventoryLog = InventoryLog::create([
            'store_id' => $senderStore->id,
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'quantity_change' => -2,
            'type' => 'remove',
        ]);
        $purchase = Purchase::create([
            'store_id' => $senderStore->id,
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost' => 15,
        ]);
        $transfer = StoreTransfer::create([
            'sender_store_id' => $senderStore->id,
            'receiver_store_id' => $receiverStore->id,
            'status' => 'completed',
        ]);
        $transferItem = StoreTransferItem::create([
            'store_transfer_id' => $transfer->id,
            'sender_product_id' => $product->id,
            'requested_quantity' => 1,
            'normalized_quantity' => 1,
            'unit_type' => 'unit',
            'cost_price' => 15,
        ]);

        $product->forceDelete();

        $this->assertNull($movement->fresh()->product_id);
        $this->assertSame('منتج تاريخي', $movement->fresh()->product_name_snapshot);
        $this->assertSame('منتج تاريخي', $inventoryLog->fresh()->product_name_snapshot);
        $this->assertSame(-2.0, $inventoryLog->fresh()->quantity_snapshot);
        $this->assertSame('منتج تاريخي', $purchase->fresh()->product_name_snapshot);
        $this->assertNull($transferItem->fresh()->sender_product_id);
        $this->assertSame('منتج تاريخي', $transferItem->fresh()->product_name_snapshot);
    }
}
