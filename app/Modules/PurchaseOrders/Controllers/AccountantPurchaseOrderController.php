<?php

namespace App\Modules\PurchaseOrders\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Services\PurchaseOrderPdfService;
use App\Modules\PurchaseOrders\Services\StorePurchaseOrderService;
use App\Modules\PurchaseOrders\Services\PurchaseOrderNotificationService;
use App\Modules\PurchaseOrders\Support\PurchaseOrderItemSorter;
use App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountantPurchaseOrderController extends Controller
{
    public function __construct(
        private StorePurchaseOrderService $orders,
        private PurchaseOrderNotificationService $notifications,
        private PurchaseOrderPdfService $pdfs,
    )
    {
    }

    public function index(Request $request)
    {
        $store = $this->accountantStore();
        $statuses = ['draft', 'sent', 'received', 'approved', 'cancelled'];
        $status = in_array($request->get('status'), $statuses, true) ? $request->get('status') : null;
        $workflowStatuses = array_keys(PurchaseOrderWorkflow::filterLabels($store->user?->name));
        $workflowStatus = in_array($request->get('workflow_status'), $workflowStatuses, true) ? $request->get('workflow_status') : null;
        $search = trim((string) $request->get('search', ''));
        $dateFrom = $request->filled('date_from') ? $request->date('date_from')->startOfDay() : now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? $request->date('date_to')->endOfDay() : now()->endOfMonth();

        $orders = StorePurchaseOrder::withCount('items')
            ->where('store_id', $store->id)
            ->where(function ($query) {
                $query->where('accountant_id', auth('accountant')->id())
                    ->orWhereNull('accountant_id');
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($workflowStatus, fn ($query) => $query->where('workflow_status', $workflowStatus))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('supplier_name', 'like', '%'.$search.'%')
                        ->orWhere('id', is_numeric($search) ? (int) $search : -1)
                        ->orWhereHas('items', function ($items) use ($search): void {
                            $items->where('custom_product_name', 'like', '%'.$search.'%')
                                ->orWhereHas('product', fn ($product) => $product->where('name', 'like', '%'.$search.'%'))
                                ->orWhereHas('matchedProduct', fn ($product) => $product->where('name', 'like', '%'.$search.'%'));
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('modules.purchase-orders.user.index', [
            'store' => $store,
            'orders' => $orders,
            'status' => $status,
            'workflowStatus' => $workflowStatus,
            'search' => $search,
            'statuses' => $statuses,
            'dateFromValue' => $dateFrom->format('Y-m-d'),
            'dateToValue' => $dateTo->format('Y-m-d'),
            'purchaseOrderContext' => 'accountant',
        ]);
    }

    public function create()
    {
        $store = $this->accountantStore();
        $products = $this->orderProducts($store);

        return view('modules.purchase-orders.user.create', [
            'store' => $store,
            'products' => $products,
            'purchaseOrderContext' => 'accountant',
        ]);
    }

    public function edit(StorePurchaseOrder $order)
    {
        $store = $this->authorizeOrder($order);
        abort_unless($order->status === 'draft' && $order->inventory_review_status === 'returned_for_edit', 403);

        $order->load(['items.product', 'items.matchedProduct']);

        return view('modules.purchase-orders.user.create', [
            'store' => $store,
            'products' => $this->orderProducts($store),
            'order' => $order,
            'purchaseOrderContext' => 'accountant',
        ]);
    }

    public function update(Request $request, StorePurchaseOrder $order)
    {
        $store = $this->authorizeOrder($order);
        abort_unless($order->status === 'draft' && $order->inventory_review_status === 'returned_for_edit', 403);

        $payload = $this->validatedOrderPayload($request, $store);
        unset($payload['supplier_name'], $payload['notes']);
        $this->orders->updateDraftOrder($order, $store->user, $payload, 'accountant', auth('accountant')->id());

        return redirect()->route('accountant.purchase-orders.index')
            ->with('success', 'تم حفظ التعديلات وإعادة الطلبية إلى '.$store->user->name.'.');
    }

    public function store(Request $request)
    {
        $store = $this->accountantStore();
        $payload = $this->validatedOrderPayload($request, $store);

        $order = $this->orders->createOrder($store, $store->user, $payload, auth('accountant')->id());
        $this->notifyOwner($store, 'طلبية توريد جديدة', 'توجد طلبية توريد بانتظار مراجعتك.', $order);

        return redirect()->route('accountant.purchase-orders.index')
            ->with('success', 'تم إرسال الطلبية إلى '.$store->user->name.' للمراجعة.');
    }

    public function show(StorePurchaseOrder $order)
    {
        $store = $this->authorizeOrder($order);
        $this->flashReturnedOrderStatus($order, $store->user?->name);
        $order->load(['items.product', 'items.matchedProduct', 'items.countAttempts', 'store', 'accountant', 'events']);
        PurchaseOrderItemSorter::sortLoadedItemsByName($order);

        return view('modules.purchase-orders.user.show', [
            'store' => $store,
            'order' => $order,
            'purchaseOrderContext' => 'accountant',
        ]);
    }

    public function inventoryCount(StorePurchaseOrder $order)
    {
        $store = $this->authorizeOrder($order);
        abort_unless(in_array($order->inventory_review_status, ['returned_to_accountant', 'count_draft'], true), 403);
        $order->load(['items.product', 'items.matchedProduct', 'store.user', 'store.accountants', 'accountant']);
        PurchaseOrderItemSorter::sortLoadedItemsByName($order);

        return view('modules.purchase-orders.accountant.inventory-count', compact('store', 'order'));
    }

    public function saveInventoryCount(Request $request, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($order);
        $validated = $request->validate([
            'action' => 'required|string|in:save,submit',
            'items' => 'required|array',
            'items.*.inventory_count_quantity' => 'nullable|numeric|min:0',
            'items.*.inventory_count_unit' => 'nullable|string|in:unit,roll,meter,piece,kit',
            'items.*.inventory_count_note' => 'nullable|string|max:40',
        ]);

        $this->orders->saveInventoryCount($order, $order->store->user, $validated['items'], $validated['action'] === 'submit', auth('accountant')->id());
        if ($validated['action'] === 'submit') {
            $this->notifyOwner($order->store, 'جرد طلبية توريد', 'تم إرسال جرد طلبية توريد للمراجعة.', $order);

            return redirect()->route('accountant.purchase-orders.index')
                ->with('success', 'تم الإرسال إلى '.$order->store->user->name.'.');
        }

        return redirect()->route('accountant.purchase-orders.show', $order->id)
            ->with('success', 'تم حفظ الجرد مؤقتًا.');
    }

    public function inventoryCountPdf(StorePurchaseOrder $order)
    {
        $store = $this->authorizeOrder($order);
        abort_unless(in_array($order->inventory_review_status, ['returned_to_accountant', 'count_draft'], true), 403);
        return $this->pdfs->download($store, $order, 'inventory-count');
    }

    public function receive(Request $request, StorePurchaseOrder $order)
    {
        $store = $this->authorizeOrder($order);
        abort_unless($order->status === 'sent', 403);

        // رسائل عربية تشغيلية حتى يعرف المحاسب الحقل الذي منع تأكيد الاستلام بدل ظهور رسالة تقنية عامة.
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', Rule::exists('store_purchase_order_items', 'id')->where(fn ($query) => $query->where('store_purchase_order_id', $order->id))],
            'items.*.quantity_received' => ['nullable', 'numeric', 'min:0'],
            'items.*.cost_price_at_receipt' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_type' => ['nullable', 'string', Rule::in(['unit', 'roll', 'meter', 'meters', 'piece', 'kit', 'default', 'normalized'])],
            'items.*.matched_product_id' => ['nullable', Rule::exists('products', 'id')->where(fn ($query) => $query->where('store_id', $store->id))],
            'items.*.receipt_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'items.required' => 'يجب إرسال بيانات الاستلام أولاً.',
            'items.*.id.required' => 'معرف عنصر الطلبية مطلوب لعملية التحديث.',
            'items.*.id.exists' => 'عنصر الطلبية المحدد غير صحيح أو لا ينتمي لهذه الطلبية.',
            'items.*.quantity_received.numeric' => 'الكمية المستلمة يجب أن تكون رقمًا.',
            'items.*.quantity_received.min' => 'الكمية المستلمة لا يمكن أن تكون أقل من صفر.',
            'items.*.cost_price_at_receipt.numeric' => 'سعر الاستلام يجب أن يكون رقمًا.',
            'items.*.cost_price_at_receipt.min' => 'سعر الاستلام لا يمكن أن يكون أقل من صفر.',
            'items.*.unit_type.in' => 'وحدة الاستلام المحددة غير صحيحة.',
            'items.*.matched_product_id.exists' => 'المنتج المقابل المختار غير صحيح أو لا يتبع هذا المتجر.',
            'items.*.receipt_notes.max' => 'ملاحظة الاستلام يجب ألا تتجاوز 1000 حرف.',
        ]);

        $items = collect($validated['items'])->keyBy(fn ($item) => (int) $item['id'])->all();
        $this->orders->receive($order, $store->user, $items, 'accountant', auth('accountant')->id());
        // يثبت المحاسب الذي أكد الاستلام حتى يظهر اسمه للمالك في مرحلة المراجعة.
        $order->update(['accountant_id' => auth('accountant')->id()]);
        $this->notifyOwner($store, 'تم تأكيد استلام طلبية', 'أكد '.auth('accountant')->user()->name.' استلام '.$order->displayName().'.', $order);

        return redirect()->route('accountant.purchase-orders.index')
            ->with('success', 'تم تأكيد الاستلام وإرساله إلى '.$store->user->name.' للمراجعة.');
    }

    public function receiptPdf(StorePurchaseOrder $order)
    {
        $store = $this->authorizeOrder($order);
        abort_unless($order->status === 'sent', 403);
        return $this->pdfs->download($store, $order, 'receipt');
    }


    private function notifyOwner(Store $store, string $title, string $message, StorePurchaseOrder $order): void
    {
        $this->notifications->afterCommit([
            'sender_id' => auth('accountant')->id(),
            'sender_type' => 'accountant',
            'target_type' => 'user',
            'target_ids' => [$store->user_id],
            'title' => $title,
            'message' => $message,
            'data' => ['purchase_order_id' => $order->id, 'store_id' => $store->id],
            'template_key' => 'purchase_order_review',
            'channel' => 'CARLED',
        ]);
    }

    private function flashReturnedOrderStatus(StorePurchaseOrder $order, ?string $ownerName): void
    {
        if (! in_array($order->workflow_status, ['returned_for_edit', 'returned_after_edit', 'returned_for_count', 'returned_after_count'], true)) {
            return;
        }

        $message = 'حالة الطلبية: '.PurchaseOrderWorkflow::label($order->workflow_status, $ownerName);
        if (trim((string) $order->inventory_review_note) !== '') {
            $message .= ' — '.$order->inventory_review_note;
        }
        session()->flash('info', $message);
    }

    private function validatedOrderPayload(Request $request, Store $store): array
    {
        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.product_id' => ['nullable', Rule::exists('products', 'id')->where(fn ($query) => $query->where('store_id', $store->id))],
            'items.*.quantity_requested' => 'nullable|numeric|min:0.01',
            'items.*.unit_type' => 'nullable|string|in:unit,roll,meter,meters,piece,kit,default,normalized',
            'items.*.receipt_notes' => 'nullable|string|max:255',
            'custom_items' => 'nullable|array',
            'custom_items.*.custom_product_name' => 'nullable|string|max:255',
            'custom_items.*.quantity_requested' => 'nullable|numeric|min:0.01',
            'custom_items.*.unit_type' => 'nullable|string|in:unit,roll,meter,meters,piece,kit,default,normalized',
            'custom_items.*.cost_price_at_order' => 'nullable|numeric|min:0',
            'custom_items.*.items_per_unit' => 'nullable|integer|min:2|max:100000',
            'custom_items.*.roll_length' => 'nullable|numeric|min:0.01|max:100000',
            'custom_items.*.receipt_notes' => 'nullable|string|max:255',
            'custom_items.*.add_to_owner_purchases' => 'nullable|boolean',
        ]);

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($item) => ! empty($item['product_id']) && ! empty($item['quantity_requested']))
            ->values()
            ->all();

        $customItems = collect($validated['custom_items'] ?? [])
            ->filter(fn ($item) => ! empty($item['custom_product_name']) && ! empty($item['quantity_requested']))
            ->values()
            ->all();

        if (empty($items) && empty($customItems)) {
            throw ValidationException::withMessages(['items' => 'يجب اختيار منتج واحد على الأقل أو إضافة منتج مخصص.']);
        }

        return [
            // يكمل المالك اسم المورد والملاحظة عند مراجعة الطلبية.
            'supplier_name' => null,
            'notes' => null,
            'items' => $items,
            'custom_items' => $customItems,
        ];
    }

    private function authorizeOrder(StorePurchaseOrder $order): Store
    {
        $store = $this->accountantStore();

        if ((int) $order->store_id !== (int) $store->id
            || (int) $order->user_id !== (int) $store->user_id
            || ($order->accountant_id && (int) $order->accountant_id !== (int) auth('accountant')->id())) {
            abort(403);
        }

        return $store;
    }

    private function accountantStore(): Store
    {
        $accountant = auth('accountant')->user();
        abort_unless($accountant && $accountant->store_id, 403);

        return Store::with('user')->findOrFail($accountant->store_id);
    }

    private function orderProducts(Store $store)
    {
        return Product::where('store_id', $store->id)
            ->orderBy('name')
            ->get(['id', 'name', 'quantity', 'min_stock', 'description', 'cost_price', 'price', 'product_type', 'usage_type', 'roll_length', 'is_splittable', 'carton_qty', 'items_per_unit']);
    }
}
