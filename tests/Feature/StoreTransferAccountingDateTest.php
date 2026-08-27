<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreTransfer;
use App\Models\User;
use App\Services\StoreTransferService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class StoreTransferAccountingDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_movements_use_request_and_approval_accounting_dates(): void
    {
        $owner = User::factory()->create(['plan_id' => null, 'welcome_shown' => true]);
        $sender = Store::factory()->create(['user_id' => $owner->id]);
        $receiver = Store::factory()->create(['user_id' => $owner->id]);
        $senderProduct = $this->product($owner, $sender, 'المنتج المرسل', 10);
        $receiverProduct = $this->product($owner, $receiver, 'المنتج المستلم', 0);
        $service = app(StoreTransferService::class);

        $transfer = $service->createTransfer($sender, $receiver, [[
            'sender_product_id' => $senderProduct->id,
            'quantity' => 1,
            'unit_type' => 'unit',
        ]], null, $owner, '2026-08-01');

        $service->approveTransfer(
            $transfer,
            [$transfer->items->first()->id => $receiverProduct->id],
            $owner,
            true,
            '2026-08-02'
        );

        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $sender->id,
            'business_date' => '2026-08-01',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $receiver->id,
            'business_date' => '2026-08-02',
        ]);
        $this->assertSame('2026-08-01', $transfer->fresh()->request_business_date->toDateString());
        $this->assertSame('2026-08-02', $transfer->fresh()->action_business_date->toDateString());
    }

    public function test_owner_approves_all_transfer_items_from_one_complete_mapping_form(): void
    {
        $owner = User::factory()->create(['plan_id' => null, 'welcome_shown' => true]);
        $sender = Store::factory()->create(['user_id' => $owner->id]);
        $receiver = Store::factory()->create(['user_id' => $owner->id]);
        $firstSenderProduct = $this->product($owner, $sender, 'مرسل أول', 10);
        $secondSenderProduct = $this->product($owner, $sender, 'مرسل ثان', 10);
        $firstReceiverProduct = $this->product($owner, $receiver, 'مستلم أول', 0);
        $secondReceiverProduct = $this->product($owner, $receiver, 'مستلم ثان', 0);

        $transfer = app(StoreTransferService::class)->createTransfer($sender, $receiver, [
            ['sender_product_id' => $firstSenderProduct->id, 'quantity' => 1, 'unit_type' => 'unit'],
            ['sender_product_id' => $secondSenderProduct->id, 'quantity' => 2, 'unit_type' => 'unit'],
        ], null, $owner, now()->toDateString());
        $transfer->load('items');

        $page = $this->actingAs($owner)->get(route('user.stores.transfers.index', $receiver));
        $page->assertOk()->assertSee('اعتماد واستلام جميع البنود (2)');
        $this->assertSame(1, substr_count($page->getContent(), 'data-transfer-approval-form'));

        $this->actingAs($owner)->post(route('user.stores.transfers.owner-approve', [$receiver, $transfer]), [
            'business_date' => now()->toDateString(),
            'receiver_product_id' => [
                $transfer->items[0]->id => $firstReceiverProduct->id,
                $transfer->items[1]->id => $secondReceiverProduct->id,
            ],
        ])->assertRedirect();

        $this->assertSame(StoreTransferService::STATUS_COMPLETED, $transfer->fresh()->status);
        $this->assertSame(1.0, (float) $firstReceiverProduct->fresh()->quantity);
        $this->assertSame(2.0, (float) $secondReceiverProduct->fresh()->quantity);
    }

    public function test_transfer_service_names_the_items_that_are_missing_receiver_mapping(): void
    {
        $owner = User::factory()->create(['plan_id' => null, 'welcome_shown' => true]);
        $sender = Store::factory()->create(['user_id' => $owner->id]);
        $receiver = Store::factory()->create(['user_id' => $owner->id]);
        $senderProduct = $this->product($owner, $sender, 'منتج بلا مقابل', 10);
        $transfer = app(StoreTransferService::class)->createTransfer($sender, $receiver, [[
            'sender_product_id' => $senderProduct->id,
            'quantity' => 1,
            'unit_type' => 'unit',
        ]], null, $owner, now()->toDateString());

        try {
            app(StoreTransferService::class)->approveTransfer($transfer, [], $owner, true, now()->toDateString());
            $this->fail('Expected receiver mapping validation to fail.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('منتج بلا مقابل', $exception->errors()['receiver_product_id'][0]);
        }
    }

    public function test_owner_transfer_page_only_contains_transfers_related_to_selected_store(): void
    {
        $owner = User::factory()->create(['plan_id' => null, 'welcome_shown' => true]);
        $first = Store::factory()->create(['user_id' => $owner->id]);
        $second = Store::factory()->create(['user_id' => $owner->id]);
        $third = Store::factory()->create(['user_id' => $owner->id]);
        $related = StoreTransfer::create(['sender_store_id' => $first->id, 'receiver_store_id' => $second->id]);
        StoreTransfer::create(['sender_store_id' => $second->id, 'receiver_store_id' => $third->id]);

        $this->actingAs($owner)
            ->get(route('user.stores.transfers.index', $first))
            ->assertOk()
            ->assertViewHas('transfers', fn ($transfers) => $transfers->pluck('id')->all() === [$related->id]);
    }

    public function test_outgoing_review_scope_waits_for_twenty_four_hours(): void
    {
        $owner = User::factory()->create();
        $sender = Store::factory()->create(['user_id' => $owner->id]);
        $receiver = Store::factory()->create(['user_id' => $owner->id]);

        StoreTransfer::create(['sender_store_id' => $sender->id, 'receiver_store_id' => $receiver->id]);
        $old = StoreTransfer::create(['sender_store_id' => $sender->id, 'receiver_store_id' => $receiver->id]);
        $old->forceFill(['created_at' => now()->subHours(25)])->saveQuietly();

        $this->assertSame([$old->id], StoreTransfer::pendingForReview()->pluck('id')->all());
    }

    public function test_owner_cannot_create_transfer_outside_current_month(): void
    {
        $owner = User::factory()->create(['plan_id' => null, 'welcome_shown' => true]);
        $sender = Store::factory()->create(['user_id' => $owner->id]);
        $receiver = Store::factory()->create(['user_id' => $owner->id]);
        $product = $this->product($owner, $sender, 'منتج التحقق من التاريخ', 10);

        $this->actingAs($owner)
            ->post(route('user.stores.transfers.store', $sender), [
                'receiver_store_id' => $receiver->id,
                'business_date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'items' => [[
                    'sender_product_id' => $product->id,
                    'quantity' => 1,
                    'unit_type' => 'unit',
                ]],
            ])
            ->assertSessionHasErrors('business_date');

        $this->assertDatabaseCount('store_transfers', 0);
    }

    private function product(User $owner, Store $store, string $name, float $quantity): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => 'transfer-product-'.$store->id.'-'.substr(md5($name), 0, 10),
            'price' => 10,
            'cost_price' => 5,
            'quantity' => $quantity,
            'status' => 'active',
            'product_type' => 'standard',
            'is_splittable' => false,
            'items_per_unit' => 1,
            'min_stock' => 0,
        ]);
    }
}
