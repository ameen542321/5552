<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProductIndexInventoryDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_index_formats_set_stock_and_shows_latest_audit_note(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = $owner->stores()->firstOrFail();
        $store->update(['status' => 'active']);

        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج طقم للاختبار',
            'slug' => 'inventory-display-set',
            'price' => 100,
            'cost_price' => 60,
            'quantity' => 1.5,
            'status' => 'active',
            'product_type' => 'standard',
            'waste_percentage' => 0,
            'roll_length' => 0,
            'is_splittable' => true,
            'items_per_unit' => 4,
            'piece_price' => 25,
            'quick_sale_default_unit' => 'unit',
            'min_stock' => 0.5,
        ]);

        Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج طقم افتراضيه الحبة',
            'slug' => 'inventory-display-piece-default',
            'price' => 150,
            'cost_price' => 90,
            'quantity' => 1,
            'status' => 'active',
            'product_type' => 'standard',
            'waste_percentage' => 0,
            'roll_length' => 0,
            'is_splittable' => true,
            'items_per_unit' => 4,
            'piece_price' => 75,
            'quick_sale_default_unit' => 'piece',
            'min_stock' => 0.5,
        ]);

        Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج بدون جرد',
            'slug' => 'inventory-display-without-audit',
            'price' => 50,
            'cost_price' => 25,
            'quantity' => 2,
            'status' => 'active',
            'product_type' => 'standard',
            'waste_percentage' => 0,
            'roll_length' => 0,
            'is_splittable' => false,
            'items_per_unit' => 1,
            'piece_price' => 0,
            'min_stock' => 1,
        ]);

        StockMovement::recordForProduct(
            $product,
            'increase',
            0,
            1.5,
            1.5,
            $owner->id,
            'تأكيد جرد المنتج — ملاحظة جرد قديمة',
            0,
            'normalized',
            '2026-01-01'
        );

        StockMovement::recordForProduct(
            $product,
            'increase',
            0,
            1.5,
            1.5,
            $owner->id,
            'تأكيد جرد المنتج — تمت مطابقة الرف',
            0,
            'normalized',
            '2026-07-26'
        );

        $response = $this->actingAs($owner)
            ->get(route('user.stores.products.index', $store));

        $response->assertOk();
        $response->assertSeeText('المخزون: 1 طقم و2 حبة');
        $response->assertSeeText('النوع: طقم');
        $response->assertSeeText('الطقم: 100 ر.س');
        $response->assertSeeText('الطقم: 60.00 ر.س');
        $response->assertSeeText('الحبة: 15.00 ر.س');
        $response->assertSeeText('النوع: حبة');
        $response->assertSeeText('المخزون: 4 حبة');
        $response->assertSeeText('الحبة: 75 ر.س');
        $response->assertSeeText('الحبة: 22.50 ر.س');
        $response->assertSeeText('الطقم: 90.00 ر.س');
        $response->assertSeeText('الكمية');
        $response->assertSeeText('الحد الأدنى');
        $response->assertSeeText('2 حبة');
        $response->assertSeeText('تأكيد الجرد:');
        $response->assertSeeText('تمت مطابقة الرف');
        $response->assertDontSeeText('ملاحظة جرد قديمة');
        $response->assertSeeText('لا يوجد جرد');
        $response->assertDontSeeText('منتج مطابق للبحث');
        $response->assertDontSeeText('آخر بيع:');
    }
}
