<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow;
use Illuminate\Http\Request;

class PurchaseOrderHealthCheckController extends Controller
{
    public function index(?Request $request = null)
    {
        $request ??= request();
        $orders = StorePurchaseOrder::withTrashed()
            ->with(['store:id,name', 'items:id,store_purchase_order_id,quantity_received,excluded_after_count,add_to_owner_purchases,owner_purchase_id,stock_quantity_before,stock_quantity_after,cost_price_before,cost_price_after'])
            // يسمح الرابط من لوحة المتابعة بفتح نتيجة طلبية واحدة مباشرة.
            ->when($request->integer('order_id'), fn ($query, $orderId) => $query->whereKey($orderId));

        // نفحص كامل البيانات على دفعات بدل إخفاء المشاكل القديمة خلف حد ثابت،
        // مع الاحتفاظ في الذاكرة بالصفوف التي تحتوي مشاكل فقط.
        $rows = collect($orders->lazyById(200)
            ->map(function (StorePurchaseOrder $order): ?array {
                $problems = PurchaseOrderWorkflow::consistencyIssues($order);
                if ($order->status === 'approved' && $order->workflow_status !== 'reversed') {
                    $missingSnapshots = $order->items->contains(function ($item): bool {
                        if ($item->excluded_after_count || (float) ($item->quantity_received ?? 0) <= 0) {
                            return false;
                        }
                        if ($item->add_to_owner_purchases) {
                            return ! $item->owner_purchase_id || $item->cost_price_after === null;
                        }

                        return $item->stock_quantity_before === null || $item->stock_quantity_after === null
                            || $item->cost_price_before === null || $item->cost_price_after === null;
                    });
                    if ($missingSnapshots) {
                        $problems[] = 'طلبية معتمدة تحتوي بندًا بلا لقطات اعتماد مكتملة.';
                    }
                }
                if ($problems === []) {
                    return null;
                }

                return [
                    'id' => $order->id,
                    'reference' => $order->referenceCode(),
                    'store' => $order->store?->name ?: 'متجر محذوف',
                    'status' => $order->status,
                    'workflow_status' => $order->workflow_status,
                    'deleted' => $order->trashed(),
                    'problems' => $problems,
                ];
            })
            ->filter()
            ->values()
            ->all());

        return view('admin.health.purchase-orders', ['rows' => $rows, 'totalIssues' => $rows->count()]);
    }
}
