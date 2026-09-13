<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PurchaseOrderHealthCheckController;
use App\Models\Store;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use Illuminate\View\View;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AdminPurchaseOrderHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_health_check_reports_inconsistent_orders_without_changing_them(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'approved',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);

        $response = app(PurchaseOrderHealthCheckController::class)->index();

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(1, $response->getData()['totalIssues']);
        $this->assertDatabaseHas('store_purchase_orders', [
            'id' => $order->id,
            'status' => 'approved',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);
    }

    public function test_health_check_does_not_hide_issues_behind_an_arbitrary_row_limit(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $problemOrder = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'approved',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);

        foreach (range(1, 500) as $index) {
            StorePurchaseOrder::create([
                'store_id' => $store->id,
                'user_id' => $owner->id,
                'status' => 'draft',
                'workflow_status' => 'pending_owner_review',
            ]);
        }

        $response = app(PurchaseOrderHealthCheckController::class)->index();

        $this->assertTrue($response->getData()['rows']->contains('id', $problemOrder->id));
    }
}
