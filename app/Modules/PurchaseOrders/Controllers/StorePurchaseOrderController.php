<?php

namespace App\Modules\PurchaseOrders\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use App\Modules\PurchaseOrders\Services\PurchaseOrderPdfService;
use App\Modules\PurchaseOrders\Services\StorePurchaseOrderService;
use App\Modules\PurchaseOrders\Support\PurchaseOrderItemSorter;
use App\Modules\PurchaseOrders\Support\PurchaseOrderSearch;
use App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow;
use App\Services\ShiftLifecycleService;
use App\Services\SupportSessionService;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePurchaseOrderController extends Controller
{
    public function __construct(
        private StorePurchaseOrderService $orders,
        private PurchaseOrderPdfService $pdfs,
    )
    {
    }

    /**
     * عرض قائمة طلبيات التوريد مع فلترة الحالة والفترة.
     */
    public function index(Request $request, Store $store)
    {
        $this->authorizeStore($store);
        $statuses = ['draft', 'sent', 'received', 'approved', 'cancelled'];
        $status = in_array($request->get('status'), $statuses, true) ? $request->get('status') : null;
        $workflowStatuses = array_keys(PurchaseOrderWorkflow::filterLabels($store->user?->name));
        $workflowStatus = in_array($request->get('workflow_status'), $workflowStatuses, true) ? $request->get('workflow_status') : null;
        $search = trim((string) $request->get('search', ''));
        $searchOrderId = PurchaseOrderSearch::orderId($search);
        $dateFrom = $request->filled('date_from') ? $request->date('date_from')->startOfDay() : now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? $request->date('date_to')->endOfDay() : now()->endOfMonth();
        $technicalSupportSession = app(SupportSessionService::class)->active($request);

        $ordersQuery = StorePurchaseOrder::withCount('items');
        if ($technicalSupportSession?->target_role === 'owner') {
            $ordersQuery->withTrashed();
        }

        $orders = $ordersQuery
            ->where('store_id', $store->id)
            ->where('user_id', auth('web')->id())
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($workflowStatus, fn ($query) => $query->where('workflow_status', $workflowStatus))
            // البحث الشامل يطابق مرجع الطلبية، المورد، وأسماء المنتجات النظامية أو المخصصة.
            ->when($search !== '', function ($query) use ($search, $searchOrderId): void {
                $query->where(function ($nested) use ($search, $searchOrderId): void {
                    $nested->where('supplier_name', 'like', '%'.$search.'%')
                        ->when($searchOrderId !== null, fn ($referenceQuery) => $referenceQuery->orWhereKey($searchOrderId))
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

        $dateFromValue = $dateFrom->format('Y-m-d');
        $dateToValue = $dateTo->format('Y-m-d');

        return view('modules.purchase-orders.user.index', compact('store', 'orders', 'status', 'statuses', 'workflowStatus', 'search', 'dateFromValue', 'dateToValue', 'technicalSupportSession'));
    }

    /**
     * فتح شاشة إنشاء الطلبية وتجهيز المنتجات المقترحة حسب نشاط المخزون والمبيعات.
     */
    public function create(Store $store)
    {
        $this->authorizeStore($store);
        $products = $this->suggestedProducts($store);

        return view('modules.purchase-orders.user.create', compact('store', 'products'));
    }

    /**
     * استقبال نموذج الإنشاء وتحويله لخدمة الطلبيات لإنشاء مسودة لا تؤثر على المخزون.
     */
    public function store(Request $request, Store $store)
    {
        $this->authorizeStore($store);
        $payload = $this->validatedOrderPayload($request, $store);

        $order = $this->orders->createOrder($store, auth('web')->user(), $payload);

        return redirect()->route('user.stores.purchase-orders.show', [$store->id, $order->id])
            ->with('success', 'تم تجهيز الطلبية كمسودة. راجعها ثم اضغط اعتماد الطلبية لإرسالها للمورد.');
    }

    /**
     * عرض الطلبية وبنودها ورسائل واتساب وخيارات الاستلام أو الاعتماد حسب الحالة الحالية.
     */
    public function show(Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $order->load(['items.product', 'items.matchedProduct', 'items.countAttempts', 'store', 'accountant', 'events']);
        PurchaseOrderItemSorter::sortLoadedItemsByName($order);
        // تحتاج نافذة الربط إلى الوصف أيضًا حتى يطابق بحث المنتجات الاسم أو الوصف.
        $products = Product::where('store_id', $store->id)->orderBy('name')->get(['id', 'name', 'description', 'cost_price']);
        $categories = Category::where('store_id', $store->id)->orderBy('name')->get(['id', 'name']);
        $ownerPurchaseCategoryId = optional($categories->first(fn (Category $category) => trim($category->name) === 'مشتريات المالك'))->id;
        $whatsappText = $this->buildWhatsappText($order);
        $currentBusinessDate = app(ShiftLifecycleService::class)->currentShiftContext($store->id)['business_date'];
        $technicalSupportSession = app(SupportSessionService::class)->active(request());
        $receiptAccountantName = $order->accountant?->name;
        if (! $receiptAccountantName && $order->receipt_actor_type === 'accountant' && $order->receipt_actor_id) {
            $receiptAccountantName = Accountant::where('store_id', $store->id)
                ->whereKey($order->receipt_actor_id)
                ->value('name');
        }

        return view('modules.purchase-orders.user.show', compact('store', 'order', 'products', 'categories', 'ownerPurchaseCategoryId', 'whatsappText', 'currentBusinessDate', 'technicalSupportSession', 'receiptAccountantName'));
    }

    /**
     * فتح شاشة تعديل المسودة فقط؛ أي طلبية مرسلة أو مستلمة لا يسمح بتعديلها من هنا.
     */
    public function edit(Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        if ($order->status !== 'draft') {
            return redirect()->route('user.stores.purchase-orders.show', [$store->id, $order->id])
                ->withErrors(['order' => 'يمكن تعديل الطلبية قبل اعتماد إرسالها فقط.']);
        }

        $order->load(['items.product', 'items.matchedProduct']);
        $products = Product::where('store_id', $store->id)
            ->orderBy('name')
            // التعديل هنا: تمت إضافة 'price' للاستعلام
            ->get(['id', 'name', 'quantity', 'min_stock', 'cost_price', 'price', 'product_type', 'usage_type', 'roll_length', 'is_splittable', 'carton_qty']);

        return view('modules.purchase-orders.user.create', compact('store', 'products', 'order'));
    }

    /**
     * حفظ تعديلات المسودة وإعادة بناء البنود من جديد داخل الخدمة.
     */
    public function update(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $payload = $this->validatedOrderPayload($request, $store);
        if (! $request->exists('supplier_name')) {
            unset($payload['supplier_name']);
        }
        if (! $request->exists('notes')) {
            unset($payload['notes']);
        }

        $updatedOrder = $this->orders->updateDraftOrder($order, auth('web')->user(), $payload);

        return redirect()->route('user.stores.purchase-orders.show', [$store->id, $updatedOrder->id])
            ->with('success', 'تم حفظ تعديلات مسودة الطلبية وتحديث تكاليف المنتجات من بياناتها الحالية.');
    }

    /**
     * تصدير الطلبية إلى PDF مع دمج ملاحظات البنود ومعلومات الكراتين عند توفرها.
     */
public function pdf(Store $store, StorePurchaseOrder $order)
{
    $this->authorizeOrder($store, $order);
    $pdfMode = request()->query('type', 'order');
    abort_unless(in_array($pdfMode, ['order', 'receipt', 'inventory', 'inventory-count'], true), 404);
    abort_unless(PurchaseOrderWorkflow::allowsPdf($pdfMode, $order->status, $order->inventory_review_status), 403);

    return $this->pdfs->download($store, $order, $pdfMode, request()->boolean('hide_prices'));
}
    /**
     * تحويل المسودة إلى طلبية مرسلة للمورد دون المساس بالمخزون.
     */
    public function markSent(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $validated = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:40'],
        ], ['supplier_name.required' => 'أدخل اسم المورد قبل إرسال الطلبية.']);
        $this->orders->markSent($order, auth('web')->user(), $validated);

        return redirect()->route('user.stores.purchase-orders.index', $store->id)
            ->with('success', 'تم إقفال الطلبية واعتمادها للإرسال للمورد، وأُبلغ المحاسب بمتابعة الاستلام.');
    }

    /**
     * التحقق من بيانات الاستلام ثم حفظها كمرحلة مراجعة قبل الاعتماد المخزني النهائي.
     */
    public function receive(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);

        // بعض حقول الواجهة ترسل رقم البند كمفتاح للمصفوفة؛ نعيد نسخه إلى id لتوحيد شكل البيانات قبل validation.
        $itemsInput = collect($request->input('items', []))
            ->mapWithKeys(function ($item, $key) {
                if (is_array($item) && empty($item['id']) && is_numeric($key)) {
                    $item['id'] = (int) $key;
                }

                return [$key => $item];
            })
            ->all();

        $request->merge(['items' => $itemsInput]);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => ['required', Rule::exists('store_purchase_order_items', 'id')->where(fn ($query) => $query->where('store_purchase_order_id', $order->id))],
            'items.*.quantity_received' => 'nullable|numeric|min:0',
            'items.*.cost_price_at_receipt' => 'nullable|numeric|min:0',
            'items.*.unit_type' => 'nullable|string|in:unit,roll,meter,meters,piece,kit,default,normalized',
            'items.*.matched_product_id' => ['nullable', Rule::exists('products', 'id')->where(fn ($query) => $query->where('store_id', $store->id))],
            'items.*.update_product_cost' => 'nullable|boolean',
            'items.*.add_to_owner_purchases' => 'nullable|boolean',
            'items.*.receipt_notes' => 'nullable|string|max:1000',
        ], [
            'items.required' => 'يجب إرسال بيانات الاستلام أولاً.',
            'items.*.id.required' => 'معرف عنصر الطلبية مطلوب لعملية التحديث.',
            'items.*.id.exists' => 'عنصر الطلبية المحدد غير صحيح أو لا ينتمي لهذه الطلبية.',
            'items.*.quantity_received.numeric' => 'الكمية المستلمة يجب أن تكون رقمًا.',
            'items.*.quantity_received.min' => 'الكمية المستلمة لا يمكن أن تكون أقل من صفر.',
            'items.*.cost_price_at_receipt.numeric' => 'سعر الاستلام يجب أن يكون رقمًا.',
            'items.*.cost_price_at_receipt.min' => 'سعر الاستلام لا يمكن أن يكون أقل من صفر.',
            'items.*.matched_product_id.exists' => 'المنتج المقابل المختار غير صحيح أو لا يتبع هذا المتجر.',
        ]);

        $items = collect($validated['items'])
            ->keyBy(fn ($item) => (int) $item['id'])
            ->all();

        $this->orders->receive($order, auth('web')->user(), $items);

        return redirect(route('user.stores.purchase-orders.show', [$store->id, $order->id]).'#inventory-approval')
            ->with('success', 'تم تأكيد مراجعة الاستلام. انتقل الآن إلى الاعتماد المخزني.');
    }

    /**
     * ربط البند بمنتج محفوظ أو إنشاء منتج مكتمل من بيانات الاستلام، دون إضافة الرصيد قبل الاعتماد المخزني.
     */
    public function storeOwnerPurchaseProduct(
        Request $request,
        Store $store,
        StorePurchaseOrder $order,
        StorePurchaseOrderItem $item
    ) {
        $this->authorizeOrder($store, $order);

        abort_unless((int) $item->store_purchase_order_id === (int) $order->id, 404);
        $canManageReceiptProduct = $order->status === 'sent'
            || ($order->status === 'received' && $order->workflow_status === 'pending_owner_receipt_review');
        abort_unless($canManageReceiptProduct, 422, 'يمكن ربط المنتج أو إنشاؤه أثناء مراجعة تأكيد الاستلام فقط.');
        abort_unless(! $item->product_id && ! $item->matched_product_id, 422, 'هذا البند مرتبط بمنتج بالفعل.');

        $request->merge([
            'product_action' => $request->input('product_action') ?: ($request->filled('existing_product_id') ? 'link' : 'create'),
        ]);
        $validated = $request->validate([
            'product_action' => ['required', Rule::in(['link', 'create'])],
            'existing_product_id' => ['nullable', Rule::requiredIf(fn () => $request->input('product_action') === 'link'), Rule::exists('products', 'id')->where(fn ($query) => $query->where('store_id', $store->id))],
            'name' => ['nullable', Rule::requiredIf(fn () => $request->input('product_action') === 'create'), 'string', 'max:255'],
            'category_id' => ['nullable', Rule::requiredIf(fn () => $request->input('product_action') === 'create'), Rule::exists('categories', 'id')->where(fn ($query) => $query->where('store_id', $store->id))],
            'owner_unit_type' => ['nullable', Rule::requiredIf(fn () => $request->input('product_action') === 'create'), Rule::in(['piece', 'kit', 'roll'])],
            'receipt_total_cost' => ['nullable', 'numeric', 'min:0'],
            'received_quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items_per_unit' => ['nullable', 'integer', 'min:2'],
            'roll_length' => ['nullable', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
            'usage_type' => ['nullable', Rule::requiredIf(fn () => $request->input('product_action') === 'create'), Rule::in([Product::USAGE_TYPE_SALE, Product::USAGE_TYPE_OWNER_PURCHASE])],
            'selling_price' => ['nullable', Rule::requiredIf(fn () => $request->input('product_action') === 'create' && $request->input('usage_type') === Product::USAGE_TYPE_SALE), 'numeric', 'min:0.01'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'piece_price' => ['nullable', 'numeric', 'min:0'],
            'carton_qty' => ['nullable', 'integer', 'min:1'],
            'waste_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quick_sale_default_unit' => ['nullable', Rule::in(['unit', 'piece'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'image' => ['nullable', 'image', 'max:2048'],
            'fractions' => ['nullable', 'array'],
            'fractions.*.option_label' => ['required_with:fractions.*.deduction_value,fractions.*.price', 'string', 'max:255'],
            'fractions.*.deduction_value' => ['required_with:fractions.*.option_label,fractions.*.price', 'numeric', 'min:0.01'],
            'fractions.*.price' => ['required_with:fractions.*.option_label,fractions.*.deduction_value', 'numeric', 'min:0'],
        ]);

        if ($validated['product_action'] === 'link') {
            $existingProduct = Product::where('store_id', $store->id)->findOrFail($validated['existing_product_id']);
            $item->update([
                'product_id' => $existingProduct->id,
                'matched_product_id' => null,
                'add_to_owner_purchases' => $existingProduct->isOwnerPurchaseOnly(),
            ]);

            return response()->json([
                'message' => 'تم ربط المنتج الموجود بالطلبية.',
                'product' => ['id' => $existingProduct->id, 'name' => $existingProduct->name],
            ]);
        }

        $normalizedName = mb_strtolower(trim($validated['name']));
        $duplicate = Product::withTrashed()->where('store_id', $store->id)
            ->get(['id', 'name'])
            ->first(fn (Product $product) => mb_strtolower(trim($product->name)) === $normalizedName);

        if ($duplicate) {
            return response()->json([
                'message' => 'هذا المنتج موجود مسبقًا في منتجات المتجر.',
                'product' => ['id' => $duplicate->id, 'name' => $duplicate->name],
            ], 409);
        }

        // القيم المدخلة في نافذة الحفظ هي الأحدث، وتفاصيل الطلبية تبقى قيمة افتراضية فقط.
        $itemsPerUnit = (int) (($validated['items_per_unit'] ?? null) ?: $item->items_per_unit ?: 0);
        $rollLength = (float) (($validated['roll_length'] ?? null) ?: $item->roll_length ?: 0);
        if ($validated['owner_unit_type'] === 'kit' && $itemsPerUnit < 2) {
            return response()->json(['message' => 'أدخل عدد حبات الطقم.'], 422);
        }
        if ($validated['owner_unit_type'] === 'roll' && $rollLength <= 0) {
            return response()->json(['message' => 'أدخل طول الرول بالمتر.'], 422);
        }

        $imagePath = $request->hasFile('image') ? $request->file('image')->store('products', 'public') : null;
        $product = DB::transaction(function () use ($validated, $store, $order, $item, $itemsPerUnit, $rollLength, $imagePath) {
            $unitType = $validated['owner_unit_type'];
            $isKit = $unitType === 'kit';
            $isRoll = $unitType === 'roll';
            $receivedQuantity = (float) ($validated['received_quantity'] ?? $item->quantity_received ?? $item->quantity_requested ?? 0);
            $receiptTotalCost = (float) ($validated['receipt_total_cost'] ?? $item->cost_price_at_receipt ?? $item->cost_price_at_order ?? 0);
            $unitCost = $receivedQuantity > 0 ? round($receiptTotalCost / $receivedQuantity, 2) : 0;
            $slugBase = Str::slug($validated['name']) ?: 'owner-purchase';
            $slug = $slugBase . '-s' . $store->id . '-po' . $order->id . '-i' . $item->id;

            $isSaleProduct = $validated['usage_type'] === Product::USAGE_TYPE_SALE;
            $product = Product::create([
                'store_id' => $store->id,
                'user_id' => auth('web')->id(),
                'category_id' => $validated['category_id'],
                'name' => trim($validated['name']),
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'price' => (float) ($validated['selling_price'] ?? 0),
                'piece_price' => $isKit ? (float) ($validated['piece_price'] ?? 0) : 0,
                'cost_price' => $unitCost,
                'quantity' => 0,
                'min_stock' => (float) ($validated['min_stock'] ?? 0),
                'barcode' => $validated['barcode'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'image' => $imagePath,
                'product_type' => $isRoll ? 'fractional' : 'standard',
                'usage_type' => $validated['usage_type'],
                'roll_length' => $isRoll ? $rollLength : 0,
                'is_splittable' => $isKit,
                'items_per_unit' => $isKit ? $itemsPerUnit : 1,
                'carton_qty' => ! $isRoll ? ($validated['carton_qty'] ?? null) : null,
                'waste_percentage' => $isRoll ? (float) ($validated['waste_percentage'] ?? 0) : 0,
                'quick_sale_default_unit' => $isKit
                    ? ($validated['quick_sale_default_unit'] ?? 'unit')
                    : 'unit',
            ]);

            $item->update([
                'product_id' => $product->id,
                'matched_product_id' => null,
                'unit_type' => $unitType,
                'add_to_owner_purchases' => ! $isSaleProduct,
            ]);

            if ($isRoll) {
                foreach ($validated['fractions'] ?? [] as $fraction) {
                    $product->fractions()->create([
                        'option_label' => $fraction['option_label'],
                        'deduction_value' => $fraction['deduction_value'],
                        'price' => $fraction['price'],
                    ]);
                }
            }

            return $product;
        });

        return response()->json([
            'message' => $validated['usage_type'] === Product::USAGE_TYPE_SALE
                ? 'تم حفظ المنتج ضمن منتجات البيع وربطه بالطلبية.'
                : 'تم حفظ المنتج ضمن مشتريات المالك وربطه بالطلبية.',
            'product' => ['id' => $product->id, 'name' => $product->name],
        ], 201);
    }


    public function returnForInventoryCount(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $validated = $request->validate([
            'inventory_review_note' => 'required|string|max:40',
            'return_action' => 'required|string|in:inventory,edit',
            'item_ids' => 'required_if:return_action,inventory|array|min:1',
            'item_ids.*' => ['integer', Rule::exists('store_purchase_order_items', 'id')->where(fn ($query) => $query->where('store_purchase_order_id', $order->id))],
        ], [
            'inventory_review_note.required' => 'أدخل ملاحظة قبل إرجاع الطلبية.',
            'item_ids.required_if' => 'حدد منتجًا واحدًا على الأقل للجرد.',
            'item_ids.min' => 'حدد منتجًا واحدًا على الأقل للجرد.',
            'item_ids.*.exists' => 'أحد المنتجات المحددة لا ينتمي إلى هذه الطلبية.',
        ]);

        $updatedOrder = $this->orders->returnForInventoryCount(
            $order,
            auth('web')->user(),
            $validated['inventory_review_note'] ?? null,
            $validated['return_action'],
            array_map('intval', $validated['item_ids'] ?? [])
        );

        if ($updatedOrder->status === 'cancelled') {
            return redirect()->route('user.stores.purchase-orders.index', $store->id)
                ->with('success', 'تم إلغاء الطلبية بعد استنفاد ثلاث مرات للإعادة والتعديل.');
        }

        return redirect()->route('user.stores.purchase-orders.index', $store->id)->with('success', $validated['return_action'] === 'inventory'
            ? 'تم إرجاع الطلبية للجرد.'
            : 'تم إرجاع الطلبية للتعديل.');
    }

    public function approveInventoryReview(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $validated = $request->validate([
            'inventory_review_note' => 'nullable|string|max:40',
        ]);

        $this->orders->approveInventoryReview($order, auth('web')->user(), $validated['inventory_review_note'] ?? null);

        return back()->with('success', 'تم اعتماد مراجعة الجرد فقط. فروقات الجرد لم تعدل المخزون.');
    }

    public function rejectInventoryItems(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', Rule::exists('store_purchase_order_items', 'id')->where(fn ($query) => $query->where('store_purchase_order_id', $order->id))],
            'reason' => ['required', 'string', 'max:40'],
        ], [
            'item_ids.required' => 'حدد منتجًا واحدًا على الأقل لإعادته للجرد.',
            'item_ids.array' => 'اختيار المنتجات المرسلة للجرد غير صالح.',
            'item_ids.min' => 'حدد منتجًا واحدًا على الأقل لإعادته للجرد.',
            'item_ids.*.exists' => 'أحد المنتجات المحددة لا ينتمي إلى هذه الطلبية.',
            'reason.required' => 'اكتب سبب إعادة المنتجات المحددة للجرد.',
        ]);
        $this->orders->rejectCountItems($order, auth('web')->user(), array_map('intval', $validated['item_ids']), $validated['reason']);
        return redirect()->route('user.stores.purchase-orders.index', $store->id)
            ->with('success', 'تم إعادة المنتجات المحددة للجرد. ستبقى الطلبية مقفلة حتى يعيد المحاسب الجرد للمالك.');
    }

    public function restoreExcludedItem(Store $store, StorePurchaseOrder $order, StorePurchaseOrderItem $item)
    {
        $this->authorizeOrder($store, $order);
        $this->orders->restoreExcludedItem($order, $item, auth('web')->user());
        return back()->with('success', 'تمت استعادة المنتج إلى الطلبية.');
    }

    public function reject(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:40']]);
        $this->orders->reject($order, auth('web')->user(), $validated['rejection_reason']);
        return back()->with('success', 'تم رفض الطلبية مع حفظ سبب الرفض.');
    }

    public function reopen(Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $this->orders->reopen($order, auth('web')->user());
        return back()->with('success', 'تمت إعادة الطلبية للمراجعة.');
    }

    /**
     * تنفيذ الاعتماد المخزني النهائي وإضافة الكميات المستلمة إلى رصيد المنتجات.
     */
    public function approve(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $validated = $request->validate([
            'business_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $openBusinessDate = app(ShiftLifecycleService::class)->currentShiftContext($store->id)['business_date'];
        $businessDate = $validated['business_date'] ?? $openBusinessDate;
        if ($businessDate !== $openBusinessDate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'business_date' => 'لا يمكن اعتماد الطلبية في فترة مغلقة. اختر اليوم المفتوح: '.$openBusinessDate.'.',
            ]);
        }
        $this->orders->approve($order, auth('web')->user(), $businessDate);

        return redirect()->route('user.stores.purchase-orders.index', $store->id)
            ->with('success', 'تم الاعتماد المخزني وتحديث المنتجات وتسجيل مشتريات المالك بنجاح.');
    }

    /**
     * إلغاء طلبية لم تصل بعد إلى مرحلة الاستلام.
     */
    public function cancel(Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $this->orders->cancel($order, auth('web')->user());

        return redirect()->route('user.stores.purchase-orders.index', $store->id)
            ->with('success', 'تم إلغاء طلبية التوريد.');
    }

    public function destroy(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);

        $request->validate(['confirmation' => ['required', Rule::in([(string) $order->id])]]);
        if (! in_array($order->status, ['cancelled', 'approved'], true)) {
            return back()->withErrors(['order' => 'يمكن حذف الطلبية الملغاة أو المعتمدة فقط.']);
        }

        $order->delete();

        return redirect()->route('user.stores.purchase-orders.index', $store->id)
            ->with('success', 'تم حذف طلبية التوريد من القائمة دون تغيير المنتجات أو المخزون.');
    }

    public function supportUpdateStatus(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $support = app(SupportSessionService::class)->active($request);
        abort_unless($support && $support->target_role === 'owner', 403);
        $transitions = PurchaseOrderWorkflow::supportTransitions();
        $validated = $request->validate([
            'workflow_status' => ['required', Rule::in(array_keys($transitions))],
            'support_note' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $supportNote = trim($validated['support_note']).' — تذكرة الدعم '.$support->ticket_reference;

        DB::transaction(function () use ($order, $validated, $transitions, $support, $supportNote): void {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            // يعاد فحص حماية الاعتماد بعد القفل لمنع تغيّر الطلبية بين الفحص والحفظ.
            if ($lockedOrder->approved_at || $lockedOrder->approval_operation_id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'workflow_status' => 'لا يمكن إعادة طلبية نُفذ اعتمادها المخزني إلى مرحلة سابقة. استخدم عملية عكس الاعتماد مع إبقاء سجلها.',
                ]);
            }
            $from = $lockedOrder->workflow_status;
            [$status, $workflowStatus] = $transitions[$validated['workflow_status']];
            $lockedOrder->update([
                'status' => $status,
                'workflow_status' => $workflowStatus,
                'cancelled_at' => $status === 'cancelled' ? ($lockedOrder->cancelled_at ?: now()) : null,
            ]);
            $lockedOrder->events()->create([
                'event' => 'support_status_corrected',
                'from_status' => $from,
                'to_status' => $workflowStatus,
                'actor_type' => 'support',
                'actor_id' => $support->admin_id,
                'note' => $supportNote,
                'data' => ['support_session_id' => $support->id],
            ]);
        });

        return back()->with('success', 'تم تصحيح حالة الطلبية بواسطة الدعم التقني دون تنفيذ حركة مخزنية.');
    }

    public function supportRestore(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $support = app(SupportSessionService::class)->active($request);
        abort_unless($support && $support->target_role === 'owner', 403);
        abort_unless((int) $order->store_id === (int) $store->id && (int) $store->user_id === (int) auth('web')->id(), 403);
        abort_unless($order->trashed(), 422, 'الطلبية غير محذوفة.');
        $validated = $request->validate(['support_note' => ['required', 'string', 'min:10', 'max:500']]);

        DB::transaction(function () use ($order, $support, $validated): void {
            $lockedOrder = StorePurchaseOrder::withTrashed()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->trashed(), 422, 'الطلبية غير محذوفة.');
            $lockedOrder->restore();
            $lockedOrder->events()->create([
                'event' => 'support_restored',
                'from_status' => 'deleted',
                'to_status' => $lockedOrder->workflow_status,
                'actor_type' => 'support',
                'actor_id' => $support->admin_id,
                'note' => trim($validated['support_note']).' — تذكرة الدعم '.$support->ticket_reference,
                'data' => ['support_session_id' => $support->id],
            ]);
        });

        return back()->with('success', 'تمت استعادة الطلبية بواسطة الدعم التقني.');
    }

    public function supportPurge(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $support = app(SupportSessionService::class)->active($request);
        abort_unless($support && $support->target_role === 'owner', 403);
        abort_unless((int) $order->store_id === (int) $store->id && (int) $store->user_id === (int) auth('web')->id(), 403);
        $request->validate([
            'confirmation' => ['required', Rule::in([$order->referenceCode()])],
            'support_note' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $supportNote = trim((string) $request->input('support_note')).' — تذكرة الدعم '.$support->ticket_reference;

        $orderName = $order->displayName();
        $orderId = $order->id;
        $products = $order->items()->whereNotNull('product_id')->pluck('product_id')
            ->merge($order->items()->whereNotNull('matched_product_id')->pluck('matched_product_id'))
            ->unique()->values()->all();

        DB::transaction(function () use ($order, $support): void {
            $lockedOrder = StorePurchaseOrder::withTrashed()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            // الحماية الحاسمة داخل القفل: لا يعتمد المنع على الحالة القديمة التي حملها Route Model Binding.
            if (in_array($lockedOrder->status, ['received', 'approved'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'order' => $lockedOrder->status === 'approved'
                        ? 'لا تحذف الطلبية المعتمدة نهائيًا. استخدم عملية عكس الاعتماد مع إبقاء سجل التدقيق.'
                        : 'لا يمكن حذف الطلبية المستلمة نهائيًا؛ يجب إبقاء سجل الاستلام للمراجعة.',
                ]);
            }
            $lockedOrder->forceDelete();
        });

        if ($support->ticket) {
            app(SupportTicketService::class)->event($support->ticket, 'purchase_order_purged', 'support', $support->admin_id, [
                'support_session_id' => $support->id,
                'purchase_order_id' => $orderId,
                'purchase_order_name' => $orderName,
                'preserved_product_ids' => $products,
                'note' => $supportNote,
            ]);
        }

        return redirect()->route('user.stores.purchase-orders.index', $store->id)
            ->with('success', 'حذف الدعم التقني الطلبية وملفاتها التابعة نهائيًا دون حذف المنتجات أو حركات التوريد.');
    }

    public function supportReverse(Request $request, Store $store, StorePurchaseOrder $order)
    {
        $this->authorizeOrder($store, $order);
        $support = app(SupportSessionService::class)->active($request);
        abort_unless($support && $support->target_role === 'owner', 403);
        $validated = $request->validate([
            'confirmation' => ['required', Rule::in([$order->referenceCode()])],
            'support_note' => ['required', 'string', 'min:10', 'max:500'],
            'business_date' => ['required', 'date_format:Y-m-d'],
        ]);
        $openBusinessDate = app(ShiftLifecycleService::class)->currentShiftContext($store->id)['business_date'];
        if ($validated['business_date'] !== $openBusinessDate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'business_date' => 'تنفذ عملية العكس في اليوم المفتوح فقط: '.$openBusinessDate.'.',
            ]);
        }
        $admin = $support->admin ?: \App\Models\User::withTrashed()->find($support->admin_id);
        abort_unless($admin?->isAdmin(), 403);
        $reason = trim($validated['support_note']).' — تذكرة الدعم '.$support->ticket_reference;
        $this->orders->reverseApproval($order, $admin, $reason, $openBusinessDate);

        return back()->with('success', 'تم عكس أثر اعتماد الطلبية مع إبقاء سجلها الكامل للمراجعة.');
    }

    /**
     * توحيد والتحقق من مدخلات الطلبية: منتجات نظامية، منتجات مخصصة، كميات، وحدات وملاحظات.
     */
    private function validatedOrderPayload(Request $request, Store $store): array
    {
        $validated = $request->validate([
            'supplier_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:40',
            'items' => 'nullable|array',
            'items.*.product_id' => ['nullable', Rule::exists('products', 'id')->where(fn ($query) => $query->where('store_id', $store->id))],
            'items.*.quantity_requested' => 'nullable|numeric|min:0.01',
            'items.*.unit_type' => 'nullable|string|in:unit,roll,meter,meters,piece,kit,default,normalized',
            'items.*.receipt_notes' => 'nullable|string|max:255',
            'custom_items' => 'nullable|array',
            'custom_items.*.custom_product_name' => 'required_with:custom_items|string|max:255',
            'custom_items.*.quantity_requested' => 'nullable|numeric|min:0.01',
            'custom_items.*.unit_type' => 'nullable|string|in:unit,roll,meter,meters,piece,kit,default,normalized',
            'custom_items.*.items_per_unit' => 'nullable|integer|min:2',
            'custom_items.*.roll_length' => 'nullable|numeric|min:0.01',
            'custom_items.*.receipt_notes' => 'nullable|string|max:255',
            'custom_items.*.cost_price_at_order' => 'nullable|numeric|min:0',
            'custom_items.*.add_to_owner_purchases' => 'nullable|boolean',
           
        ]);

        $rawItems = collect($validated['items'] ?? []);
        $missingItemQuantity = $rawItems->first(fn ($item) => ! empty($item['product_id']) && empty($item['quantity_requested']));
        if ($missingItemQuantity) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'يجب إدخال الكمية المطلوبة لكل منتج تم اختياره، بما في ذلك منتجات مشتريات المالك.']);
        }

        $items = $rawItems
            ->filter(fn ($item) => ! empty($item['product_id']) && ! empty($item['quantity_requested']))
            ->values()
            ->all();

        $rawCustomItems = collect($validated['custom_items'] ?? []);
        $missingCustomQuantity = $rawCustomItems->first(fn ($item) => ! empty($item['custom_product_name']) && empty($item['quantity_requested']));
        if ($missingCustomQuantity) {
            throw \Illuminate\Validation\ValidationException::withMessages(['custom_items' => 'يجب إدخال الكمية المطلوبة لكل منتج مخصص.']);
        }
        if ($rawCustomItems->contains(fn ($item) => ($item['unit_type'] ?? null) === 'kit' && (int) ($item['items_per_unit'] ?? 0) < 2)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['custom_items' => 'يجب إدخال عدد حبات الطقم لكل منتج مخصص بوحدة طقم.']);
        }
        if ($rawCustomItems->contains(fn ($item) => ($item['unit_type'] ?? null) === 'roll' && (float) ($item['roll_length'] ?? 0) <= 0)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['custom_items' => 'يجب إدخال طول الرول لكل منتج مخصص بوحدة رول.']);
        }

        $customItems = $rawCustomItems
            ->filter(fn ($item) => ! empty($item['custom_product_name']))
            ->values()
            ->all();

        if (empty($items) && empty($customItems)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'يجب اختيار منتج واحد على الأقل أو إضافة منتج مخصص.']);
        }

        return [
            'supplier_name' => $validated['supplier_name'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'items' => $items,
            'custom_items' => $customItems,
        ];
    }

    private function suggestedProducts(Store $store)
    {
        return Product::where('store_id', $store->id)
            ->orderByRaw("CASE WHEN product_type = 'fractional' AND roll_length > 0 THEN ((quantity / roll_length) <= min_stock) ELSE (quantity <= min_stock) END DESC")
            ->orderBy('quantity')
            ->orderBy('name')
            ->get(['id', 'name', 'quantity', 'min_stock', 'description', 'cost_price', 'price', 'product_type', 'usage_type', 'roll_length', 'is_splittable', 'carton_qty']);
    }

    private function buildWhatsappText(StorePurchaseOrder $order): string
    {
        $lines = [
            'السلام عليكم ورحمة الله وبركاته',
            'طلب بضاعة جديد من متجر: ' . $order->store->name,
            '',
            'المنتجات المطلوبة:',
        ];

        foreach ($order->items as $index => $item) {
            $note = trim((string) ($item->receipt_notes ?? ''));
            $lines[] = ($index + 1) . '. ' . $item->productName() . ': ' . $this->formatQuantityForMessage((float) $item->quantity_requested, $item->unit_type, $item->product) . ($note !== '' ? ' - ' . $note : '');
        }

        $lines[] = '';
        $lines[] = 'هذه الرسالة تم إرسالها عبر CARLED.';

        return implode("\n", $lines);
    }

    private function formatQuantityForMessage(float $quantity, ?string $unitType, ?Product $product = null): string
{
    // 1. تحقق ذكي: هل المنتج عادي وله سعة كرتون؟ وهل الكمية المطلوبة تساوي أو تتجاوز كرتون واحد على الأقل؟
    if ($product && $product->carton_qty > 0 && $quantity >= $product->carton_qty && ($unitType === 'piece' || empty($unitType) && $product->product_type !== 'fractional')) {
        
        $cartonQty = (int) $product->carton_qty;
        $totalPieces = (int) $quantity;

        $cartons = floor($totalPieces / $cartonQty); // الكراتين الكاملة
        $remainingPieces = $totalPieces % $cartonQty; // الحبات المتبقية

        $textParts = [];
        if ($cartons > 0) {
            $textParts[] = $cartons . ' كرتون';
        }
        if ($remainingPieces > 0) {
            // تحديد المسمى حسب نوع الوحدة (طقم أو حبة)
            $unitName = ($product->is_splittable) ? 'طقم' : 'حبة';
            $textParts[] = $remainingPieces . ' ' . $unitName;
        }

        if (!empty($textParts)) {
            return implode(' و ', $textParts); // سينتج مثلاً: "1 كرتون و 2 حبة" ولن يظهر أي كسور
        }
    }

    // 2. الحسبة الافتراضية القديمة (للرولات، الأطقم الفرط، المنتجات بدون كرتون، والمنتجات المخصصة)
    $quantityText = $quantity > 0
        ? rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.')
        : 'كمية غير محددة';

    $unit = $this->unitLabel($unitType, $product);

    return trim($quantityText . ' ' . $unit);
}

    private function unitLabel(?string $unitType, ?Product $product = null): string
    {
        $matchedLabel = match ($unitType) {
            'meter', 'meters' => 'متر',
            'piece' => 'حبة',
            'kit' => 'طقم',
            'roll' => 'رول',
            default => '',
        };

        if ($matchedLabel === '' && $product && isset($product->product_type)) {
            return $product->product_type === 'fractional' ? 'متر' : 'حبة';
        }

        return $matchedLabel;
    }


    private function authorizeOrder(Store $store, StorePurchaseOrder $order): void
    {
        $this->authorizeStore($store);
        if ((int) $order->store_id !== (int) $store->id || (int) $order->user_id !== (int) auth('web')->id()) {
            abort(403);
        }
    }

    private function authorizeStore(Store $store): void
    {
        if ((int) $store->user_id !== (int) auth('web')->id()) {
            abort(403);
        }
    }
}
