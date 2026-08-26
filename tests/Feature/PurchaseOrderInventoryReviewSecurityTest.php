<?php

namespace Tests\Feature;

use App\Models\Accountant;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderInventoryReviewSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_create_page_does_not_render_stock_or_cost_values(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'كشاف اختبار حساس',
            'price' => 7654.32,
            'cost_price' => 8765.43,
            'quantity' => 98765.432,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);

        $response = $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.create'));

        $response->assertOk();

        $decodedHtml = html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString(
            json_encode('كشاف اختبار حساس', JSON_THROW_ON_ERROR),
            $decodedHtml
        );
        $response->assertDontSee('98765.432', false);
        $response->assertDontSee('8765.43', false);
        $response->assertDontSee('7654.32', false);
        $response->assertDontSee('الكمية الموجودة', false);
    }

    public function test_accountant_cannot_open_another_accountants_purchase_order(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $otherAccountant = $this->createAccountant($owner, $store, 'محاسب آخر');
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $otherAccountant->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'draft',
        ]);

        $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.show', $order->id))
            ->assertForbidden();
    }

    public function test_accountant_cannot_download_another_accountants_receipt_pdf(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $otherAccountant = $this->createAccountant($owner, $store, 'محاسب PDF آخر');
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $otherAccountant->id,
            'supplier_name' => 'مورد PDF',
            'status' => 'sent',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);

        $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.receipt.pdf', $order))
            ->assertForbidden();
    }

    public function test_accountant_cannot_mutate_or_count_another_accountants_order(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $otherAccountant = $this->createAccountant($owner, $store, 'محاسب مسارات آخر');
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $otherAccountant->id,
            'status' => 'sent',
            'workflow_status' => 'pending_receipt_confirmation',
            'inventory_review_status' => 'returned_to_accountant',
        ]);

        $this->actingAs($accountant, 'accountant');
        $this->get(route('accountant.purchase-orders.edit', $order))->assertForbidden();
        $this->put(route('accountant.purchase-orders.update', $order), [])->assertForbidden();
        $this->get(route('accountant.purchase-orders.inventory-count', $order))->assertForbidden();
        $this->post(route('accountant.purchase-orders.inventory-count.save', $order), [])->assertForbidden();
        $this->get(route('accountant.purchase-orders.inventory-count.pdf', $order))->assertForbidden();
        $this->post(route('accountant.purchase-orders.receive', $order), [])->assertForbidden();
    }

    public function test_owner_pdf_endpoint_rejects_document_outside_its_stage(): void
    {
        [$owner, $store] = $this->ownerStoreAndAccountant();
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد مستند خاطئ',
            'status' => 'received',
            'workflow_status' => 'pending_inventory_approval',
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.pdf', [$store, $order, 'type' => 'order']))
            ->assertForbidden();
    }

    public function test_owner_can_filter_orders_by_the_operational_workflow_status(): void
    {
        [$owner, $store] = $this->ownerStoreAndAccountant();
        StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد مراجعة الاستلام',
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
        ]);
        StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد الاعتماد المخزني',
            'status' => 'received',
            'workflow_status' => 'pending_inventory_approval',
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.index', [
                $store,
                'workflow_status' => 'pending_owner_receipt_review',
            ]))
            ->assertOk()
            ->assertSee('مورد مراجعة الاستلام')
            ->assertDontSee('مورد الاعتماد المخزني');
    }

    public function test_owner_receipt_review_displays_the_reason_confirmation_failed(): void
    {
        // يحمي هذا الاختبار ظهور سبب الفشل داخل شاشة المالك المركزة بدل اختفائه بعد إعادة التوجيه.
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'supplier_name' => 'مورد يحتاج ربط منتج',
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
            'received_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'منتج غير مربوط',
            'quantity_requested' => 2,
            'quantity_received' => 2,
            'unit_type' => 'unit',
            'cost_price_at_order' => 20,
            'cost_price_at_receipt' => 20,
            'add_to_owner_purchases' => false,
        ]);

        $response = $this->actingAs($owner)->from(
            route('user.stores.purchase-orders.show', [$store, $order])
        )->post(route('user.stores.purchase-orders.receive', [$store, $order]), [
            'items' => [
                $item->id => [
                    'id' => $item->id,
                    'quantity_received' => 2,
                    'cost_price_at_receipt' => 20,
                    'unit_type' => 'unit',
                ],
            ],
        ]);

        $response->assertRedirect(route('user.stores.purchase-orders.show', [$store, $order]));
        $response->assertSessionHasErrors('items');

        $this->get(route('user.stores.purchase-orders.show', [$store, $order]))
            ->assertOk()
            ->assertSee('تعذر تنفيذ الإجراء', false)
            ->assertSee('اربط المنتج (منتج غير مربوط)', false);
    }

    public function test_accountant_receipt_page_displays_all_validation_errors(): void
    {
        // يجب أن يرى المحاسب جميع الحقول غير الصحيحة في المحاولة نفسها، لا أول خطأ فقط.
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'supplier_name' => 'مورد أخطاء التحقق',
            'status' => 'sent',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'منتج تحقق المحاسب',
            'quantity_requested' => 2,
            'unit_type' => 'unit',
            'cost_price_at_order' => 20,
            'add_to_owner_purchases' => true,
        ]);

        $showRoute = route('accountant.purchase-orders.show', $order);
        $response = $this->actingAs($accountant, 'accountant')->from($showRoute)->post(
            route('accountant.purchase-orders.receive', $order),
            [
                'items' => [
                    $item->id => [
                        'id' => $item->id,
                        'quantity_received' => 'ليست رقمًا',
                        'cost_price_at_receipt' => 'ليست رقمًا',
                        'unit_type' => 'unit',
                    ],
                ],
            ]
        );

        $response->assertRedirect($showRoute);
        $response->assertSessionHasErrors([
            "items.{$item->id}.quantity_received",
            "items.{$item->id}.cost_price_at_receipt",
        ]);

        $this->get($showRoute)
            ->assertOk()
            ->assertSee('تعذر تنفيذ الإجراء', false)
            ->assertSee('الكمية المستلمة يجب أن تكون رقمًا.', false)
            ->assertSee('سعر الاستلام يجب أن يكون رقمًا.', false);
    }

    public function test_product_searches_are_distinct_and_existing_products_are_searchable_by_description(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج باسم مختصر',
            'description' => 'وصف فريد للعثور على المنتج',
            'price' => 30,
            'cost_price' => 20,
            'quantity' => 5,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
            'received_at' => now(),
        ]);
        StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'بند يحتاج إلى الربط',
            'quantity_requested' => 1,
            'quantity_received' => 1,
            'unit_type' => 'unit',
            'cost_price_at_order' => 20,
            'cost_price_at_receipt' => 20,
            'add_to_owner_purchases' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.create', $store))
            ->assertOk()
            ->assertSee('البحث في منتجات المتجر لإضافة منتج', false)
            ->assertSee('البحث داخل بنود الطلبية الحالية فقط', false);

        $this->get(route('user.stores.purchase-orders.show', [$store, $order]))
            ->assertOk()
            ->assertSee('ملخص مراجعة الاستلام', false)
            ->assertDontSee('عدّلها المحاسب', false)
            ->assertDontSee('تحتاج ربطًا', false)
            ->assertSee('id="receipt-review" data-order-id="'.$order->id.'"', false)
            ->assertSee('البحث داخل بنود الاستلام الحالية فقط', false)
            ->assertSee('البحث في منتجات المتجر المحفوظة', false)
            ->assertSee('name="product_action" value="link" checked', false)
            ->assertSee('إنشاء منتج جديد', false)
            ->assertSee('حبة / وحدة مفردة', false)
            ->assertSee('طقم يمكن بيعه طقمًا أو حبة', false)
            ->assertSee('رول يُخزن ويباع بالمتر', false)
            ->assertSee('سعر بيع الطقم الكامل', false)
            ->assertSee('عدد حبات الطقم', false)
            ->assertSee('عدد الحبات داخل الكرتون', false)
            ->assertSee('نسبة هالك الرول/المتر', false)
            ->assertSee('خيارات بيع الرول بالمتر', false)
            ->assertSee('صورة المنتج (اختياري)', false)
            ->assertDontSee('ابحث عن القسم', false)
            ->assertSee('data-search="'.$product->name.' '.$product->description.'"', false)
            ->assertSee('لن تضاف الكميات إلى المخزون في هذه الخطوة.', false)
            ->assertSee('اعتماد مراجعة الاستلام والمتابعة', false);
    }

    public function test_accountant_receipt_defaults_explain_kit_quantity_and_total_cost(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'طقم اختبار الاستلام',
            'price' => 150,
            'cost_price' => 100,
            'quantity' => 10,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
            'is_splittable' => true,
            'items_per_unit' => 10,
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'sent',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 2,
            'unit_type' => 'kit',
            'cost_price_at_order' => 200,
        ]);

        $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.show', $order))
            ->assertOk()
            ->assertSee('المرحلة الحالية:', false)
            ->assertSee('الطقم الواحد يساوي 10 حبة.', false)
            ->assertSee('name="items['.$item->id.'][quantity_received]" value="2"', false)
            ->assertSee('name="items['.$item->id.'][cost_price_at_receipt]" value="200"', false)
            ->assertSee('طقم — سعر الواحدة المتوقع 100.00 ر.س', false)
            ->assertSee('حبة — سعر الواحدة المتوقع 10.00 ر.س', false);

        $this->post(route('accountant.purchase-orders.receive', $order), [
            'items' => [$item->id => [
                'id' => $item->id,
                'quantity_received' => 2,
                'cost_price_at_receipt' => 200,
                'unit_type' => 'kit',
            ]],
        ])->assertRedirect(route('accountant.purchase-orders.index'));

        $this->assertDatabaseHas('store_purchase_orders', [
            'id' => $order->id,
            'accountant_id' => $accountant->id,
        ]);
        $this->actingAs($owner, 'web')
            ->get(route('user.stores.purchase-orders.show', [$store, $order]))
            ->assertOk()
            ->assertSee($accountant->name, false)
            ->assertDontSee('المحاسب<br><strong class="ui-title">غير محدد', false);
    }

    public function test_opening_a_returned_order_flashes_its_arabic_status_and_note(): void
    {
        [$owner, $store] = $this->ownerStoreAndAccountant();
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'draft',
            'workflow_status' => 'returned_for_edit',
            'inventory_review_status' => 'returned_for_edit',
            'inventory_review_note' => 'عدّل كمية المنتج',
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.show', [$store, $order]))
            ->assertOk()
            ->assertSee('حالة الطلبية: معادة للتعديل — عدّل كمية المنتج', false);
    }

    public function test_owner_sees_inventory_difference_but_accountant_show_page_does_not(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج فرق الجرد',
            'price' => 20,
            'cost_price' => 10,
            'quantity' => 10,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'sent',
            'workflow_status' => 'returned_after_count',
            'inventory_review_status' => 'pending_owner_after_count',
            'inventory_submitted_at' => now(),
        ]);
        StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 3,
            'unit_type' => 'unit',
            'cost_price_at_order' => 10,
            'inventory_count_quantity' => 8,
            'inventory_count_unit' => 'unit',
            'system_quantity_snapshot' => 10,
            'inventory_snapshot_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.show', [$store->id, $order->id]))
            ->assertOk()
            ->assertSee('نقص', false)
            ->assertSee('10.000', false);

        $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.show', $order->id))
            ->assertOk()
            ->assertDontSee('نقص', false)
            ->assertDontSee('10.000', false);
    }

    public function test_inventory_count_shows_unit_choices_only_for_convertible_products(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $products = collect([
            ['name' => 'منتج قطعة واحدة', 'product_type' => 'standard', 'is_splittable' => false],
            ['name' => 'منتج طقم', 'product_type' => 'standard', 'is_splittable' => true, 'items_per_unit' => 2],
            ['name' => 'منتج رول', 'product_type' => 'fractional', 'roll_length' => 30],
        ])->map(fn (array $attributes) => Product::create(array_merge([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'price' => 20,
            'cost_price' => 10,
            'quantity' => 10,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ], $attributes)));
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'status' => 'draft',
            'workflow_status' => 'returned_for_count',
            'inventory_review_status' => 'returned_to_accountant',
        ]);

        foreach ($products as $product) {
            StorePurchaseOrderItem::create([
                'store_purchase_order_id' => $order->id,
                'product_id' => $product->id,
                'quantity_requested' => 1,
                'unit_type' => 'unit',
                'cost_price_at_order' => 10,
                'inventory_count_required' => true,
            ]);
        }

        $this->actingAs($accountant, 'accountant')
            ->get(route('accountant.purchase-orders.inventory-count', $order))
            ->assertOk()
            ->assertSee('الطقم = 2 حبة', false)
            ->assertSee('الرول = 30.00 متر', false)
            ->assertSee('name="items['.$order->items->firstWhere('product_id', $products[0]->id)->id.'][inventory_count_unit]" value="unit"', false)
            ->assertSee('منتج قطعة واحدة')
            ->assertSee('منتج طقم')
            ->assertSee('منتج رول');

        $this->assertSame(2, substr_count($response->getContent(), 'وحدة الجرد'));
    }

    public function test_inventory_paper_view_does_not_include_system_snapshot_or_difference(): void
    {
        [$owner, $store, $accountant] = $this->ownerStoreAndAccountant();
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج PDF الجرد',
            'price' => 20,
            'cost_price' => 10,
            'quantity' => 77,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'accountant_id' => $accountant->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'draft',
            'workflow_status' => 'returned_for_count',
            'inventory_review_status' => 'returned_to_accountant',
        ]);
        StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 4,
            'unit_type' => 'unit',
            'cost_price_at_order' => 10,
            'inventory_count_quantity' => 8,
            'inventory_count_unit' => 'unit',
            'system_quantity_snapshot' => 77,
            'inventory_snapshot_at' => now(),
        ]);

        $order->load(['items.product', 'store.user', 'accountant']);
        $store = $order->store;
        $html = view('modules.purchase-orders.inventory-count-pdf', compact('order', 'store'))->render();

        $this->assertStringContainsString('منتج PDF الجرد', $html);
        $this->assertStringContainsString('كمية الجرد', $html);
        $this->assertStringNotContainsString('لقطة النظام', $html);
        $this->assertStringNotContainsString('الفرق', $html);
        $this->assertStringNotContainsString('77', $html);
    }

    private function ownerStoreAndAccountant(): array
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = Store::factory()->create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);
        $accountant = $this->createAccountant($owner, $store, 'محاسب الاختبار');

        return [$owner, $store, $accountant];
    }

    private function createAccountant(User $owner, Store $store, string $name): Accountant
    {
        $employee = Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => $name,
            'phone' => '0500000000',
            'salary' => 0,
            'status' => 'active',
        ]);

        return Accountant::create([
            'employee_id' => $employee->id,
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'name' => $name,
            'email' => 'accountant-' . uniqid() . '@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);
    }
}
