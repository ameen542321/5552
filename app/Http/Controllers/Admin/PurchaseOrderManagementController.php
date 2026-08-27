<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLimitSetting;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderEvent;
use App\Modules\PurchaseOrders\Services\PurchaseOrderLimitService;
use App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseOrderManagementController extends Controller
{
    /**
     * لوحة قراءة موحدة للأدمن؛ تجمع المتابعة والتقارير والإعدادات من دون تنفيذ
     * أي انتقال أو حركة مخزنية على الطلبية.
     */
    public function index(Request $request, PurchaseOrderLimitService $limits)
    {
        $validated = $request->validate([
            'store_id' => ['nullable', 'integer', Rule::exists('stores', 'id')],
            'status' => ['nullable', Rule::in(['draft', 'sent', 'received', 'approved', 'cancelled'])],
            'workflow_status' => ['nullable', Rule::in(array_keys(PurchaseOrderWorkflow::labels()))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $dateFrom = Carbon::parse($validated['date_from'] ?? now()->startOfMonth())->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'] ?? now()->endOfMonth())->endOfDay();

        // نشتق بداية المرحلة من آخر حدث انتقل إلى المرحلة الحالية، لا من updated_at؛
        // لأن تعديل ملاحظة أو بيانات مساعدة لا يعني أن عمر المرحلة بدأ من جديد.
        $base = StorePurchaseOrder::query()
            ->with(['store:id,name', 'accountant:id,name'])
            ->withCount('items')
            ->addSelect(['stage_started_at' => StorePurchaseOrderEvent::query()
                ->select('created_at')
                ->whereColumn('store_purchase_order_id', 'store_purchase_orders.id')
                ->whereColumn('to_status', 'store_purchase_orders.workflow_status')
                ->latest('created_at')
                ->limit(1)])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($validated['store_id'] ?? null, fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['workflow_status'] ?? null, fn ($query, $status) => $query->where('workflow_status', $status))
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('supplier_name', 'like', '%'.$search.'%')
                        ->orWhere('id', is_numeric($search) ? (int) $search : -1);
                });
            });

        // نسخة مستقلة للتقارير حتى لا تؤثر pagination في المجاميع والمتوسطات المعروضة.
        $reportOrders = (clone $base)->with('items:id,store_purchase_order_id,price_variance')->get();
        $orders = $base->latest()->paginate(25)->withQueryString();
        $orders->getCollection()->each(function (StorePurchaseOrder $order): void {
            $stageStartedAt = $order->stage_started_at ? Carbon::parse($order->stage_started_at) : $order->created_at;
            $order->setAttribute('stage_age_hours', max(0, $stageStartedAt?->diffInHours(now()) ?? 0));
            $order->setAttribute('delay_threshold_hours', $this->delayThreshold($order->workflow_status));
            $order->setAttribute('integrity_issue_count', count(PurchaseOrderWorkflow::consistencyIssues($order)));
        });

        // فروقات التكلفة تقسم إلى زيادة ونقص حتى لا يلغي أحدهما الآخر في الإجمالي.
        $reports = [
            'orders_count' => $reportOrders->count(),
            'average_creation_to_send_hours' => $this->averageHours($reportOrders, 'created_at', 'sent_at'),
            'average_send_to_receive_hours' => $this->averageHours($reportOrders, 'sent_at', 'received_at'),
            'average_receive_to_approve_hours' => $this->averageHours($reportOrders, 'received_at', 'approved_at'),
            'variance_items_count' => $reportOrders->sum(fn ($order) => $order->items->filter(fn ($item) => abs((float) $item->price_variance) > 0.01)->count()),
            'positive_variance_total' => $reportOrders->sum(fn ($order) => $order->items->sum(fn ($item) => max(0, (float) $item->price_variance))),
            'negative_variance_total' => abs($reportOrders->sum(fn ($order) => $order->items->sum(fn ($item) => min(0, (float) $item->price_variance)))),
        ];

        return view('admin.purchase-orders.index', [
            'orders' => $orders,
            'stores' => Store::orderBy('name')->get(['id', 'name']),
            'reports' => $reports,
            'workflowLabels' => PurchaseOrderWorkflow::labels(),
            'globalSetting' => $limits->global(),
            'storeSettings' => PurchaseOrderLimitSetting::whereNotNull('store_id')->with('store:id,name')->orderBy('store_id')->get(),
            'filters' => $validated,
            'dateFromValue' => $dateFrom->toDateString(),
            'dateToValue' => $dateTo->toDateString(),
        ]);
    }

    public function updateGlobalLimit(Request $request, PurchaseOrderLimitService $limits)
    {
        $validated = $this->validateLimit($request, false);
        $limits->global()->update([
            'weekly_limit' => $validated['weekly_limit'],
            'counted_statuses' => $validated['counted_statuses'],
        ]);

        return back()->with('success', 'تم تحديث الحد الأسبوعي الافتراضي وحالات الاحتساب.');
    }

    public function updateStoreLimit(Request $request)
    {
        $validated = $this->validateLimit($request, true);
        $store = Store::findOrFail($validated['store_id']);
        // وجود قيمة استثناء يعني ضرورة حفظ الأدمن والسبب والانتهاء؛ وعند إزالة
        // الاستثناء تصفر بياناته حتى يعود المتجر تلقائيًا إلى حده الخاص.
        PurchaseOrderLimitSetting::updateOrCreate(['store_id' => $store->id], [
            'weekly_limit' => $validated['weekly_limit'],
            'counted_statuses' => $validated['counted_statuses'],
            'exception_weekly_limit' => $validated['exception_weekly_limit'] ?? null,
            'exception_expires_at' => $validated['exception_expires_at'] ?? null,
            'exception_reason' => $validated['exception_reason'] ?? null,
            'exception_admin_id' => ! empty($validated['exception_weekly_limit']) ? auth()->id() : null,
        ]);

        return back()->with('success', 'تم حفظ حد المتجر والاستثناء الإداري المؤقت.');
    }

    private function validateLimit(Request $request, bool $allowException): array
    {
        // الحقول المؤقتة ممنوعة في نموذج الإعداد العام، ومشروطة ببعضها في إعداد المتجر.
        return $request->validate([
            'weekly_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'store_id' => [$allowException ? 'required' : 'prohibited', 'integer', Rule::exists('stores', 'id')],
            'counted_statuses' => ['required', 'array', 'min:1'],
            'counted_statuses.*' => ['required', Rule::in(['draft', 'sent', 'received', 'approved', 'cancelled'])],
            'exception_weekly_limit' => [$allowException ? 'nullable' : 'prohibited', 'integer', 'min:1', 'max:100'],
            'exception_expires_at' => [$allowException ? 'required_with:exception_weekly_limit' : 'prohibited', 'nullable', 'date', 'after:now'],
            'exception_reason' => [$allowException ? 'required_with:exception_weekly_limit' : 'prohibited', 'nullable', 'string', 'min:10', 'max:500'],
        ]);
    }

    private function averageHours($orders, string $from, string $to): ?float
    {
        $values = $orders->filter(fn ($order) => $order->{$from} && $order->{$to})
            ->map(fn ($order) => $order->{$from}->diffInMinutes($order->{$to}) / 60);

        return $values->isEmpty() ? null : round($values->average(), 1);
    }

    private function delayThreshold(?string $workflowStatus): int
    {
        // حدود تشغيلية للعرض فقط؛ لا تشغل جدولة ولا ترسل إشعارًا تلقائيًا.
        return match ($workflowStatus) {
            'pending_owner_review', 'returned_for_edit', 'returned_for_count', 'returned_after_count' => 24,
            'pending_receipt_confirmation' => 72,
            'pending_owner_receipt_review', 'pending_inventory_approval' => 12,
            default => 0,
        };
    }
}
