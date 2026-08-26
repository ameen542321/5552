<?php

namespace Tests\Unit;

use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class StorePurchaseOrderDisplayNameTest extends TestCase
{
    public function test_it_uses_supplier_name_and_short_date_instead_of_database_id(): void
    {
        $order = new StorePurchaseOrder([
            'supplier_name' => 'موزع الشمال',
        ]);
        $order->id = 19;
        $order->created_at = Carbon::create(2026, 8, 6);

        $this->assertSame('طلبية موزع الشمال 6-8', $order->displayName());
        $this->assertStringNotContainsString('19', $order->displayName());
    }

    public function test_it_uses_store_and_creator_until_supplier_is_added(): void
    {
        $order = new StorePurchaseOrder(['supplier_name' => null]);
        $order->created_at = Carbon::create(2026, 8, 6);
        $order->setRelation('store', new \App\Models\Store(['name' => 'النور']));
        $order->setRelation('accountant', new \App\Models\Accountant(['name' => 'أحمد']));

        $this->assertSame('طلبية النور 6-8 أحمد', $order->displayName());
    }

    public function test_it_has_a_stable_reference_code(): void
    {
        $order = new StorePurchaseOrder();
        $order->id = 42;
        $order->store_id = 7;
        $order->created_at = Carbon::create(2026, 8, 8);

        $this->assertSame('PO-7-2026-00042', $order->referenceCode());
    }
}
