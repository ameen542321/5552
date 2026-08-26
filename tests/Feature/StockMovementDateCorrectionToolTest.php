<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class StockMovementDateCorrectionToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_preview_and_correct_only_exactly_matching_store_movements(): void
    {
        $owner = User::factory()->create([
            'plan_id' => null,
            'welcome_shown' => true,
        ]);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $otherStore = Store::factory()->create(['user_id' => $owner->id]);
        $movement = $this->createMovement($owner, $store, 'توريد طلبية محمد', '2026-08-01 14:25:30');
        $otherMovement = $this->createMovement($owner, $otherStore, 'توريد طلبية محمد', '2026-08-01 14:25:30');

        $filters = [
            'store_id' => $store->id,
            'note' => 'توريد طلبية محمد',
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-20',
            'time_mode' => 'preserve',
        ];

        $this->actingAs($owner)
            ->get(route('user.tools.stock-movement-dates.index', $filters))
            ->assertOk()
            ->assertViewHas('movements', fn ($movements) => $movements->pluck('id')->all() === [$movement->id]);

        $this->actingAs($owner)
            ->post(route('user.tools.stock-movement-dates.update'), $filters)
            ->assertRedirect();

        $this->assertSame('2026-08-20 14:25:30', $movement->fresh()->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-20 14:25:30', $movement->fresh()->updated_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-01 14:25:30', $otherMovement->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_owner_cannot_use_the_tool_for_another_owners_store(): void
    {
        $owner = User::factory()->create(['plan_id' => null, 'welcome_shown' => true]);
        $otherOwner = User::factory()->create(['plan_id' => null, 'welcome_shown' => true]);
        $otherStore = Store::factory()->create(['user_id' => $otherOwner->id]);

        $this->actingAs($owner)
            ->post(route('user.tools.stock-movement-dates.update'), [
                'store_id' => $otherStore->id,
                'note' => 'توريد طلبية محمد',
                'from_date' => '2026-08-01',
                'to_date' => '2026-08-20',
                'time_mode' => 'preserve',
            ])
            ->assertSessionHasErrors('store_id');
    }

    public function test_month_audit_is_read_only_and_uses_sales_view(): void
    {
        $owner = User::factory()->create(['plan_id' => null, 'welcome_shown' => true]);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $movement = $this->createMovement($owner, $store, 'حركة تدقيق شهرية', '2026-08-01 10:00:00');
        Sale::create([
            'store_id' => $store->id,
            'sale_type' => 'credit',
            'products_total' => 100,
            'labor_total' => 0,
            'final_total' => 100,
            'total' => 100,
            'paid_amount' => 40,
            'cash_amount' => 40,
            'card_amount' => 0,
            'remaining_amount' => 60,
            'business_date' => '2026-08-01',
        ]);

        $this->actingAs($owner)
            ->get(route('user.tools.stock-movement-dates.index', [
                'store_id' => $store->id,
                'audit_month' => '2026-08',
            ]))
            ->assertOk()
            ->assertSee('عمليات البيع خلال الشهر')
            ->assertSee('آجل')
            ->assertSee('60.00')
            ->assertDontSee('حركات المنتجات خلال الشهر');

        $this->assertSame('2026-08-01 10:00:00', $movement->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_month_audit_lists_sale_items_whose_products_were_deleted(): void
    {
        $owner = User::factory()->create(['plan_id' => null, 'welcome_shown' => true]);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج سيحذف',
            'slug' => 'deleted-sale-product',
            'price' => 25,
            'cost_price' => 10,
            'quantity' => 2,
            'status' => 'active',
            'product_type' => 'standard',
            'is_splittable' => false,
            'items_per_unit' => 1,
            'min_stock' => 0,
        ]);
        $sale = Sale::create([
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'products_total' => 25,
            'labor_total' => 0,
            'final_total' => 25,
            'total' => 25,
            'paid_amount' => 25,
            'cash_amount' => 25,
            'card_amount' => 0,
            'remaining_amount' => 0,
            'business_date' => '2026-08-01',
            'description' => 'بيع منتج قديم',
        ]);
        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 25,
            'total' => 25,
            'unit_type' => 'unit',
        ]);
        $product->delete();

        $this->actingAs($owner)
            ->get(route('user.tools.stock-movement-dates.index', [
                'store_id' => $store->id,
                'audit_month' => '2026-08',
            ]))
            ->assertOk()
            ->assertSee('مبيعات مرتبطة بمنتجات محذوفة')
            ->assertSee('#'.$sale->id)
            ->assertSee('#'.$item->id)
            ->assertSee('#'.$product->id)
            ->assertSee('بيع منتج قديم');
    }

    private function createMovement(User $owner, Store $store, string $note, string $timestamp): StockMovement
    {
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج '.$store->id,
            'slug' => 'tool-product-'.$store->id,
            'price' => 10,
            'cost_price' => 5,
            'quantity' => 2,
            'status' => 'active',
            'product_type' => 'standard',
            'is_splittable' => false,
            'items_per_unit' => 1,
            'min_stock' => 0,
        ]);

        $movement = StockMovement::recordForProduct(
            $product,
            'increase',
            2,
            0,
            2,
            $owner->id,
            $note,
            2,
            'unit',
            '2026-08-01'
        );
        $movement->timestamps = false;
        $movement->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();

        return $movement->fresh();
    }
}
