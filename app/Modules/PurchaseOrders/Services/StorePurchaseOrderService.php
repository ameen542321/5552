<?php

namespace App\Modules\PurchaseOrders\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\StockMovement;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderEvent;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderCountAttempt;
use App\Modules\PurchaseOrders\Support\PurchaseOrderCostCalculator;
use App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow;
use App\Models\User;
use App\Services\LogService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StorePurchaseOrderService
{
    public function __construct(
        private ?PurchaseOrderCostCalculator $costCalculator = null,
        private ?PurchaseOrderNotificationService $notifications = null,
        private ?PurchaseOrderLimitService $limits = null,
    )
    {
        $this->costCalculator ??= new PurchaseOrderCostCalculator();
        $this->notifications ??= new PurchaseOrderNotificationService();
        $this->limits ??= new PurchaseOrderLimitService();
    }

    /**
     * إنشاء طلبية توريد جديدة كمسودة فقط؛ هذه المرحلة لا تعدل رصيد المخزون.
     * يتم حفظ رأس الطلبية ثم إنشاء البنود بالتكلفة المحسوبة وقت الإنشاء.
     */
    public function createOrder(Store $store, User $user, array $payload, ?int $accountantId = null): StorePurchaseOrder
    {
        $this->ensureOwner($store, $user);

        return DB::transaction(function () use ($store, $user, $payload, $accountantId) {
            Store::whereKey($store->id)->lockForUpdate()->firstOrFail();
            $weekStartsAt = now()->startOfWeek(CarbonInterface::SATURDAY);
            $weekEndsAt = $weekStartsAt->copy()->addDays(6)->endOfDay();
            // يقرأ الحارس الحد الفعال داخل قفل المتجر لمنع طلبين متزامنين من تجاوز الحد.
            $limitSetting = $this->limits->forStore($store);
            $weeklyLimit = $limitSetting->effectiveWeeklyLimit();
            $countedStatuses = $limitSetting->effectiveCountedStatuses();
            $weeklyOrdersCount = StorePurchaseOrder::where('store_id', $store->id)
                // لا تدخل الحالات المستبعدة إداريًا في العدد الأسبوعي.
                ->whereIn('status', $countedStatuses)
                ->whereBetween('created_at', [$weekStartsAt, $weekEndsAt])
                ->count();
            if ($weeklyOrdersCount >= $weeklyLimit) {
                throw ValidationException::withMessages([
                    'order' => 'وصل المتجر إلى الحد الأسبوعي: '.$weeklyLimit.' طلبيات محتسبة من السبت إلى الجمعة.',
                ]);
            }

            $order = StorePurchaseOrder::create([
                'store_id' => $store->id,
                'user_id' => $user->id,
                'accountant_id' => $accountantId,
                'supplier_name' => $payload['supplier_name'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'status' => 'draft',
                'workflow_status' => 'pending_owner_review',
            ]);

            $this->replaceDraftItems($order, $store, $payload);
            $this->recordEvent($order, 'created', null, $order->workflow_status, $accountantId ? 'accountant' : 'user', $accountantId ?: $user->id);

            return $order->fresh(['store', 'items.product', 'items.matchedProduct']);
        });
    }

    /**
     * تعديل مسودة الطلبية قبل إرسالها للمورد.
     * نقفل الطلبية داخل transaction، ثم نحذف البنود القديمة ونعيد بناءها حتى تعكس آخر بيانات المنتجات والتكاليف.
     */
    public function updateDraftOrder(StorePurchaseOrder $order, User $user, array $payload, string $actorType = 'user', ?int $actorId = null): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);

        return DB::transaction(function () use ($order, $payload, $actorType, $actorId) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->load('store');

            if ($lockedOrder->status !== 'draft') {
                throw ValidationException::withMessages(['order' => 'يمكن تعديل الطلبية قبل اعتماد إرسالها فقط.']);
            }
            PurchaseOrderWorkflow::assertAllows($lockedOrder, 'edit_draft');

            $lockedOrder->update([
                'supplier_name' => array_key_exists('supplier_name', $payload) ? $payload['supplier_name'] : $lockedOrder->supplier_name,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $lockedOrder->notes,
            ]);

            $oldItems = $lockedOrder->items()->get();
            $oldItemNames = $oldItems->map(fn (StorePurchaseOrderItem $item) => $item->productName())->values();
            $oldItemSnapshots = $oldItems->mapWithKeys(fn (StorePurchaseOrderItem $item) => [mb_strtolower(trim($item->productName())) => [
                'name' => $item->productName(),
                'quantity' => (float) $item->quantity_requested,
                'unit' => (string) ($item->unit_type ?: 'unit'),
                'note' => (string) ($item->receipt_notes ?? ''),
                'inventory' => [
                    'required' => (bool) $item->inventory_count_required,
                    'count' => $item->inventory_count_quantity,
                    'unit' => $item->inventory_count_unit,
                    'note' => $item->inventory_count_note,
                    'snapshot' => $item->system_quantity_snapshot,
                    'snapshot_at' => $item->inventory_snapshot_at,
                    'submitted_at' => $item->inventory_count_submitted_at,
                    'submitted_by' => $item->inventory_count_submitted_by,
                    'attempt' => (int) $item->inventory_count_attempt,
                ],
            ]]);
            $returnedForEditOnly = $lockedOrder->inventory_review_status === 'returned_for_edit';
            $requiresFreshInventoryCount = in_array($lockedOrder->inventory_review_status, ['returned_to_accountant', 'count_draft'], true);
            $preserveInventoryReview = in_array($lockedOrder->inventory_review_status, ['returned_to_accountant', 'count_draft', 'pending_owner_after_count'], true);
            $lockedOrder->items()->delete();
            $this->replaceDraftItems($lockedOrder, $lockedOrder->store, $payload);
            $newItems = $lockedOrder->items()->get();
            if ($preserveInventoryReview) {
                foreach ($newItems as $newItem) {
                    $previous = $oldItemSnapshots->get(mb_strtolower(trim($newItem->productName())));
                    $inventory = $previous['inventory'] ?? null;
                    if (! $inventory) {
                        continue;
                    }
                    $newItem->update([
                        'inventory_count_required' => $inventory['required'],
                        'inventory_count_quantity' => $inventory['count'],
                        'inventory_count_unit' => $inventory['unit'],
                        'inventory_count_note' => $inventory['note'],
                        'system_quantity_snapshot' => $inventory['snapshot'],
                        'inventory_snapshot_at' => $inventory['snapshot_at'],
                        'inventory_count_submitted_at' => $inventory['submitted_at'],
                        'inventory_count_submitted_by' => $inventory['submitted_by'],
                        'inventory_count_attempt' => $inventory['attempt'],
                    ]);
                }
            }
            $newItemNames = $newItems->map(fn (StorePurchaseOrderItem $item) => $item->productName())->values();
            $newItemSnapshots = $newItems->mapWithKeys(fn (StorePurchaseOrderItem $item) => [mb_strtolower(trim($item->productName())) => [
                'name' => $item->productName(),
                'quantity' => (float) $item->quantity_requested,
                'unit' => (string) ($item->unit_type ?: 'unit'),
                'note' => (string) ($item->receipt_notes ?? ''),
            ]]);
            $itemChanges = $newItemSnapshots->map(function (array $current, string $key) use ($oldItemSnapshots) {
                $previous = $oldItemSnapshots->get($key);
                if (! $previous) {
                    return ['type' => 'added', 'name' => $current['name'], 'before' => null, 'after' => $current];
                }
                if ($previous['quantity'] === $current['quantity']
                    && $previous['unit'] === $current['unit']
                    && $previous['note'] === $current['note']) {
                    return null;
                }

                return ['type' => 'modified', 'name' => $current['name'], 'before' => $previous, 'after' => $current];
            })->filter()->values();
            $removedItemChanges = $oldItemSnapshots
                ->reject(fn (array $previous, string $key) => $newItemSnapshots->has($key))
                ->map(fn (array $previous) => ['type' => 'removed', 'name' => $previous['name'], 'before' => $previous, 'after' => null])
                ->values();
            $itemChanges = $itemChanges->concat($removedItemChanges)->values()->all();
            foreach ($newItemNames->diff($oldItemNames) as $name) {
                $this->recordEvent($lockedOrder, 'item_added', null, null, $actorType, $actorId ?: $lockedOrder->user_id, null, ['product_name' => $name]);
            }
            foreach ($oldItemNames->diff($newItemNames) as $name) {
                $this->recordEvent($lockedOrder, 'item_deleted', null, null, $actorType, $actorId ?: $lockedOrder->user_id, null, ['product_name' => $name]);
            }

            if ($requiresFreshInventoryCount) {
                $lockedOrder->update([
                    'inventory_review_status' => 'returned_to_accountant',
                    'inventory_draft_saved_at' => null,
                    'inventory_submitted_at' => null,
                ]);
            } elseif ($returnedForEditOnly) {
                $lockedOrder->update([
                    'inventory_review_status' => null,
                    'inventory_returned_at' => null,
                    'workflow_status' => 'returned_after_edit',
                ]);
            }

            $this->recordEvent(
                $lockedOrder,
                'items_updated',
                null,
                $lockedOrder->workflow_status,
                $actorType,
                $actorId ?: $lockedOrder->user_id,
                null,
                ['changes' => $itemChanges]
            );

            return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
        });
    }

    /**
     * اعتماد إرسال الطلبية للمورد: ينقل الحالة من draft إلى sent ويقفل التعديل.
     * لا يتم هنا إدخال أي كمية إلى المخزون؛ الإضافة الفعلية تتم لاحقاً عند الاعتماد المخزني.
     */
    public function markSent(StorePurchaseOrder $order, User $user, array $attributes = []): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);

        return DB::transaction(function () use ($order, $user, $attributes) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->load('store');

            if ($lockedOrder->status !== 'draft') {
                throw ValidationException::withMessages(['order' => 'يمكن إرسال الطلبية من حالة المسودة فقط.']);
            }
            PurchaseOrderWorkflow::assertAllows($lockedOrder, 'mark_sent');

            $supplierName = array_key_exists('supplier_name', $attributes)
                ? trim((string) $attributes['supplier_name'])
                : trim((string) $lockedOrder->supplier_name);
            if ($supplierName === '') {
                throw ValidationException::withMessages(['supplier_name' => 'أدخل اسم المورد قبل إرسال الطلبية.']);
            }

            if ($lockedOrder->inventory_review_status && $lockedOrder->inventory_review_status !== 'approved') {
                throw ValidationException::withMessages(['order' => 'يجب إكمال مراجعة الجرد واعتمادها قبل إرسال الطلبية للمورد.']);
            }

            $from = $lockedOrder->workflow_status;
            $lockedOrder->update([
                'supplier_name' => $supplierName,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $lockedOrder->notes,
                'status' => 'sent',
                'workflow_status' => 'pending_receipt_confirmation',
                'sent_at' => now(),
            ]);
            $this->recordEvent($lockedOrder, 'sent_to_supplier', $from, 'pending_receipt_confirmation', 'user', $user->id);

            $accountantIds = $lockedOrder->accountant_id
                ? [$lockedOrder->accountant_id]
                : $lockedOrder->store->accountants()->where('status', 'active')->pluck('id')->all();
            $notificationData = [
                'sender_id' => $user->id,
                'sender_type' => 'user',
                'target_type' => 'accountants',
                'target_ids' => $accountantIds,
                'title' => 'طلبية بانتظار تأكيد الاستلام',
                'message' => $lockedOrder->displayName().' جاهزة لتسجيل الكميات والتكاليف التي وصلت من المورد.',
                'data' => ['purchase_order_id' => $lockedOrder->id, 'store_id' => $lockedOrder->store_id],
                'template_key' => 'purchase_order_receipt_required',
                'channel' => 'CARLED',
            ];
            $this->notifications->afterCommit($notificationData);

            return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
        });
    }

    /**
     * تسجيل بيانات الاستلام بعد وصول البضاعة من المورد.
     * يتم حفظ الكمية المستلمة، وحدة الاستلام، تكلفة الاستلام، وفروقات السعر فقط دون تحديث المخزون.
     */
    public function receive(StorePurchaseOrder $order, User $user, array $items, string $actorType = 'user', ?int $actorId = null): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);
        if (! in_array($order->status, ['sent', 'received'], true)) {
            throw ValidationException::withMessages(['order' => 'يجب إرسال الطلبية للمورد قبل تسجيل أو مراجعة الاستلام.']);
        }

        return DB::transaction(function () use ($order, $items, $actorType, $actorId) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedOrder->status, ['sent', 'received'], true)) {
                throw ValidationException::withMessages(['order' => 'لم تعد الطلبية متاحة لمراجعة الاستلام.']);
            }
            PurchaseOrderWorkflow::assertAllows($lockedOrder, $actorType === 'accountant' ? 'receive_accountant' : 'receive_owner');
            $lockedOrder->load(['items.product', 'items.matchedProduct', 'store']);
            $receiptChanges = [];

            foreach ($lockedOrder->items as $item) {
                if ($item->excluded_after_count) {
                    continue;
                }
                $incoming = $items[$item->id] ?? [];
                $hasExplicitReceiptPrice = array_key_exists('cost_price_at_receipt', $incoming) && $incoming['cost_price_at_receipt'] !== null && $incoming['cost_price_at_receipt'] !== '';
                $receiptPrice = $hasExplicitReceiptPrice
                    ? (float) $incoming['cost_price_at_receipt']
                    : null;

                $isOwnerPurchaseTemplate = $item->product?->isOwnerPurchaseOnly() || $item->matchedProduct?->isOwnerPurchaseOnly();
                // سعر الطلب المحفوظ يكفي للمخصص ومشتريات المالك؛ نطلب سعرًا جديدًا فقط إن لم يوجد أي سعر سابق.
                if ($item->add_to_owner_purchases && ! $isOwnerPurchaseTemplate && ($receiptPrice === null || $receiptPrice <= 0) && (float) $item->cost_price_at_order <= 0) {
                    throw ValidationException::withMessages(['items' => 'سعر الاستلام إلزامي لصفوف مشتريات المالك المخصصة قبل تأكيد الاستلام.']);
                }
                $receivedUnitType = $incoming['unit_type'] ?? $item->unit_type;

                $receivedQuantity = array_key_exists('quantity_received', $incoming)
                    && $incoming['quantity_received'] !== null
                    && $incoming['quantity_received'] !== ''
                        ? (float) $incoming['quantity_received']
                        : (float) $item->quantity_requested;
                // نحسب التكلفة المتوقعة حسب وحدة الاستلام حتى تظهر فروقات المورد بدقة.
                $expectedCostForReceived = $this->costCalculator->receiptLineCost($item, $receivedQuantity, $receivedUnitType, $incoming['matched_product_id'] ?? null);
                $receiptPrice ??= $expectedCostForReceived;
                $hasReceiptPrice = $receiptPrice > 0;
                $variance = $receiptPrice - $expectedCostForReceived;
                $variancePercent = ($hasReceiptPrice && $expectedCostForReceived > 0) ? ($variance / $expectedCostForReceived) * 100 : 0;

                if ($actorType === 'accountant') {
                    $changedFields = [];
                    if (abs($receivedQuantity - (float) $item->quantity_requested) > 0.0001) {
                        $changedFields[] = 'quantity';
                    }
                    if ((string) $receivedUnitType !== (string) $item->unit_type) {
                        $changedFields[] = 'unit';
                    }
                    if ($hasExplicitReceiptPrice && abs($variance) > 0.01) {
                        $changedFields[] = 'cost';
                    }
                    if (! empty($incoming['receipt_notes']) && $incoming['receipt_notes'] !== $item->receipt_notes) {
                        $changedFields[] = 'note';
                    }

                    if ($changedFields !== []) {
                        $receiptChanges[(string) $item->id] = [
                            'fields' => $changedFields,
                            'quantity_requested' => (float) $item->quantity_requested,
                            'quantity_received' => $receivedQuantity,
                            'unit_before' => $item->unit_type,
                            'unit_received' => $receivedUnitType,
                            'cost_expected' => $expectedCostForReceived,
                            'cost_received' => $receiptPrice,
                        ];
                    }
                }

                if ($actorType === 'user' && ! $item->add_to_owner_purchases && ! $isOwnerPurchaseTemplate && $hasExplicitReceiptPrice && abs($variance) > 0.01 && empty($incoming['update_product_cost'])) {
                    throw ValidationException::withMessages([
                        'items' => "سعر الاستلام للمنتج ({$item->productName()}) يختلف عن التكلفة المسجلة. يجب على المالك تحديد ما إذا كان سيحدث تكلفة المنتج.",
                    ]);
                }

                $item->update([
                    'quantity_received' => $receivedQuantity,
                    'unit_type' => $receivedUnitType,
                    'cost_price_at_receipt' => $receiptPrice,
                    'matched_product_id' => $incoming['matched_product_id'] ?? $item->matched_product_id,
                    'price_variance' => $variance,
                    'price_variance_percent' => $variancePercent,
                    'update_product_cost' => ! empty($incoming['update_product_cost']),
                    'add_to_owner_purchases' => array_key_exists('add_to_owner_purchases', $incoming)
                        ? ! empty($incoming['add_to_owner_purchases'])
                        : $item->add_to_owner_purchases,
                    'receipt_notes' => $incoming['receipt_notes'] ?? $item->receipt_notes,
                ]);
            }

            $lockedOrder->load('items');

            $unresolvedItem = $actorType === 'user'
                ? $lockedOrder->items->first(fn (StorePurchaseOrderItem $item) =>
                    ! $item->excluded_after_count
                    && ! $item->product_id
                    && ! $item->matched_product_id
                    && ! $item->add_to_owner_purchases
                )
                : null;
            if ($unresolvedItem) {
                throw ValidationException::withMessages([
                    'items' => "اربط المنتج ({$unresolvedItem->productName()}) بمنتج موجود، أو أنشئه كمنتج بيع، أو حدده كمشتريات مالك قبل تأكيد المراجعة.",
                ]);
            }

            $from = $lockedOrder->workflow_status;
            $accountantTakesPriority = $actorType === 'accountant' && $lockedOrder->receipt_actor_type !== 'accountant';
            $isFirstConfirmation = $lockedOrder->received_at === null;
            $replaceConfirmationAttribution = $isFirstConfirmation || $accountantTakesPriority;
            $receiptActorType = $replaceConfirmationAttribution ? $actorType : $lockedOrder->receipt_actor_type;
            $receiptActorId = $replaceConfirmationAttribution
                ? ($actorId ?: $lockedOrder->user_id)
                : $lockedOrder->receipt_actor_id;
            $lockedOrder->update([
                'status' => 'received',
                'workflow_status' => $actorType === 'accountant' ? 'pending_owner_receipt_review' : 'pending_inventory_approval',
                'received_at' => $replaceConfirmationAttribution ? now() : $lockedOrder->received_at,
                'receipt_actor_type' => $receiptActorType,
                'receipt_actor_id' => $receiptActorId,
            ]);
            $this->recordEvent(
                $lockedOrder,
                $replaceConfirmationAttribution ? 'receipt_confirmed' : 'receipt_review_updated',
                $from,
                $actorType === 'accountant' ? 'pending_owner_receipt_review' : 'pending_inventory_approval',
                $actorType,
                $actorId ?: $lockedOrder->user_id,
                null,
                ['item_changes' => $receiptChanges]
            );

            return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
        });
    }

    /**
     * الاعتماد المخزني النهائي: هذه هي المرحلة الوحيدة التي تزيد رصيد المنتجات.
     * يتم التحقق من الربط وفروقات التكلفة، ثم تحويل الوحدات (حبة/طقم/متر/رول) عبر Product::increaseStock.
     */
    public function approve(StorePurchaseOrder $order, User $user, string $businessDate): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);

        return DB::transaction(function () use ($order, $user, $businessDate) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status === 'approved') {
                return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
            }

            if ($lockedOrder->status !== 'received') {
                throw ValidationException::withMessages(['order' => 'يجب تأكيد الاستلام قبل الاعتماد المخزني.']);
            }
            if ($lockedOrder->workflow_status !== 'pending_inventory_approval') {
                throw ValidationException::withMessages(['order' => 'يجب أن يعتمد المالك مراجعة تأكيد الاستلام قبل الاعتماد المخزني.']);
            }
            PurchaseOrderWorkflow::assertAllows($lockedOrder, 'approve');

            if (in_array($lockedOrder->inventory_review_status, ['returned_to_accountant', 'count_draft', 'pending_owner_after_count'], true)) {
                throw ValidationException::withMessages(['order' => 'يجب إكمال مراجعة الجرد أو اعتمادها من المالك قبل الاعتماد المخزني.']);
            }

            $lockedOrder->load(['items.product', 'items.matchedProduct', 'store']);

            foreach ($lockedOrder->items as $item) {
                if ($item->excluded_after_count) {
                    continue;
                }
                $quantity = (float) ($item->quantity_received ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                $productId = $item->product_id ?: $item->matched_product_id;
                if (! $productId && ! $item->add_to_owner_purchases) {
                    throw ValidationException::withMessages(['items' => "يجب ربط المنتج المخصص ({$item->productName()}) بمنتج مقابل قبل اعتماد وإغلاق الطلبية."]);
                }

                $receiptPrice = (float) ($item->cost_price_at_receipt ?? 0);
                // نحسب التكلفة المتوقعة حسب وحدة الاستلام حتى تظهر فروقات المورد بدقة.
                $expectedCostForReceived = $this->costCalculator->receiptLineCost($item, $quantity, $item->unit_type ?: 'unit', $productId);
                $supplyNote = 'توريد طلبية ' . ($lockedOrder->supplier_name ?: '#' . $lockedOrder->id);
                $selectedProduct = $item->product ?: $item->matchedProduct;
                $isOwnerPurchaseTemplate = $selectedProduct?->isOwnerPurchaseOnly() ?? false;

                // أسطر مشتريات المالك أو المنتجات المعرفة كمشتريات مالك تسجل كشراء مستقل ولا تدخل في المخزون.
                if ($item->add_to_owner_purchases || $isOwnerPurchaseTemplate) {
                    $purchase = Purchase::create([
                        'store_id' => $lockedOrder->store_id,
                        'user_id' => $user->id,
                        'product_id' => $selectedProduct?->id,
                        'purchase_name' => $item->productName(),
                        'quantity' => $quantity,
                        'cost' => $receiptPrice > 0 ? $receiptPrice : $expectedCostForReceived,
                        'description' => $supplyNote . ($isOwnerPurchaseTemplate ? ' - منتج مشتريات مالك محفوظ' : ''),
                        'business_date' => $businessDate,
                    ]);
                    $item->update([
                        'cost_price_before' => null,
                        'cost_price_after' => $purchase->cost,
                        'owner_purchase_id' => $purchase->id,
                    ]);
                } else {
                    // قفل المنتج يمنع تضارب تحديثات المخزون عند اعتماد طلبيات متزامنة.
                    $product = Product::where('store_id', $lockedOrder->store_id)->whereKey($productId)->sellable()->lockForUpdate()->firstOrFail();
                    $stockBefore = (float) $product->getRawOriginal('quantity');
                    $costBefore = (float) ($product->cost_price ?? 0);
                    $product->increaseStock($quantity, $supplyNote, $user->id, $item->unit_type ?: 'unit', $businessDate);

                    if ($item->update_product_cost && $item->cost_price_at_receipt !== null) {
                        $newCost = $this->costCalculator->normalizedProductCostFromReceipt(
                            $product,
                            $receiptPrice,
                            $quantity,
                            $item->unit_type ?: 'unit'
                        );
                        $product->update([
                            'cost_price' => $newCost,
                        ]);
                        $product->refresh();

                        if (abs($costBefore - $newCost) > 0.0001) {
                            app(LogService::class)->add('product_price_changed', 'تم تحديث تكلفة المنتج من طلبية توريد: ' . $product->name, $product, [
                                'product_name' => $product->name,
                                'old_price' => (float) $product->price,
                                'new_price' => (float) $product->price,
                                'old_cost_price' => $costBefore,
                                'new_cost_price' => $newCost,
                                'latest_receipt_unit_cost' => $this->costCalculator->normalizedProductCostFromReceipt($product, $receiptPrice, $quantity, $item->unit_type ?: 'unit'),
                                'source_type' => 'purchase_order',
                                'source_name' => $lockedOrder->supplier_name ?: 'طلبية توريد #' . $lockedOrder->id,
                                'purchase_order_id' => $lockedOrder->id,
                            ]);
                        }
                    }
                    $item->update([
                        'stock_quantity_before' => $stockBefore,
                        'stock_quantity_after' => (float) $product->getRawOriginal('quantity'),
                        'cost_price_before' => $costBefore,
                        'cost_price_after' => (float) ($product->cost_price ?? $costBefore),
                    ]);
                }
            }

            foreach ($lockedOrder->items->where('inventory_count_attempt', '>', 0) as $countedItem) {
                $countedProduct = $countedItem->product ?: $countedItem->matchedProduct;
                if (! $countedProduct || $countedItem->add_to_owner_purchases) {
                    continue;
                }
                $balance = (float) $countedProduct->getRawOriginal('quantity');
                StockMovement::recordForProduct(
                    $countedProduct,
                    'increase',
                    0,
                    $balance,
                    $balance,
                    $user->id,
                    'تأكيد جرد — '.$lockedOrder->displayName(),
                    0,
                    'normalized',
                    $businessDate
                );
            }

            if (! $lockedOrder->items->contains(fn (StorePurchaseOrderItem $item) => (float) ($item->quantity_received ?? 0) > 0)) {
                throw ValidationException::withMessages(['items' => 'يجب إدخال كمية مستلمة واحدة على الأقل قبل اعتماد وإغلاق الطلبية.']);
            }

            $lockedOrder->load('items');
            $missingSnapshots = $lockedOrder->items->contains(function (StorePurchaseOrderItem $item) {
                if ((float) ($item->quantity_received ?? 0) <= 0) {
                    return false;
                }

                $selectedProduct = $item->product ?: $item->matchedProduct;
                if ($item->add_to_owner_purchases || ($selectedProduct?->isOwnerPurchaseOnly() ?? false)) {
                    return is_null($item->owner_purchase_id) || is_null($item->cost_price_after);
                }

                return is_null($item->stock_quantity_before)
                    || is_null($item->stock_quantity_after)
                    || is_null($item->cost_price_before)
                    || is_null($item->cost_price_after);
            });

            if ($missingSnapshots) {
                throw ValidationException::withMessages(['items' => 'تعذر حفظ سجل الاعتماد المخزني بالكامل، لم يتم إغلاق الطلبية.']);
            }

            $lockedOrder->update([
                'status' => 'approved',
                'workflow_status' => 'approved_and_supplied',
                'approved_at' => now(),
                'approved_business_date' => $businessDate,
                'approval_operation_id' => $lockedOrder->approval_operation_id ?: (string) \Illuminate\Support\Str::uuid(),
                'final_notice_until' => now()->addHours(24),
            ]);
            $this->recordEvent($lockedOrder, 'inventory_approved', 'pending_inventory_approval', 'approved_and_supplied', 'user', $user->id, null, ['business_date' => $businessDate]);
            $this->notifications->afterCommit([
                'sender_id' => $user->id,
                'sender_type' => 'user',
                'target_type' => 'accountants',
                'target_ids' => $lockedOrder->accountant_id
                    ? [$lockedOrder->accountant_id]
                    : $lockedOrder->store->accountants()->where('status', 'active')->pluck('id')->all(),
                'title' => 'تم اعتماد وتوريد الطلبية',
                'message' => 'تم اعتماد وتوريد '.$lockedOrder->displayName().'.',
                'data' => ['purchase_order_id' => $lockedOrder->id, 'store_id' => $lockedOrder->store_id, 'expires_at' => $lockedOrder->final_notice_until?->toIso8601String()],
                'template_key' => 'purchase_order_approved_and_supplied',
                'channel' => 'CARLED',
            ]);

            return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
        });
    }

    /**
     * إرجاع الطلبية إلى دورة جرد مراجعة لا تعدل المخزون ولا تكشف كميات النظام للمحاسب.
     */
    public function returnForInventoryCount(StorePurchaseOrder $order, User $user, ?string $note = null, string $returnAction = 'inventory', array $itemIds = []): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages(['order' => 'تتم مراجعة الجرد قبل إرسال الطلبية للمورد فقط.']);
        }
        if ($order->inventory_review_status === 'approved') {
            throw ValidationException::withMessages(['order' => 'اعتمدت مراجعة الجرد؛ لا يمكن إعادة الطلبية للتعديل أو الجرد مرة أخرى.']);
        }

        if (! $note || ! trim($note)) {
            throw ValidationException::withMessages(['inventory_review_note' => 'أدخل ملاحظة قبل إعادة الطلبية.']);
        }

        return DB::transaction(function () use ($order, $note, $returnAction, $itemIds) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->load(['items.product', 'items.matchedProduct', 'store']);
            if ($lockedOrder->status !== 'draft' || $lockedOrder->inventory_review_status === 'approved') {
                throw ValidationException::withMessages(['order' => 'لم تعد الطلبية متاحة للإعادة للتعديل أو الجرد.']);
            }
            PurchaseOrderWorkflow::assertAllows($lockedOrder, $returnAction === 'inventory' ? 'return_for_count' : 'return_for_edit');

            if ($returnAction === 'edit' && (int) $lockedOrder->edit_return_count >= 3) {
                $from = $lockedOrder->workflow_status;
                $lockedOrder->update([
                    'status' => 'cancelled',
                    'workflow_status' => 'cancelled',
                    'cancelled_at' => now(),
                    'inventory_review_note' => $note,
                ]);
                $this->recordEvent($lockedOrder, 'cancelled_after_edit_limit', $from, 'cancelled', 'user', $lockedOrder->user_id, $note, [
                    'edit_return_count' => (int) $lockedOrder->edit_return_count,
                ]);
                $this->notifications->afterCommit([
                    'sender_id' => $lockedOrder->store->user_id,
                    'sender_type' => 'user',
                    'target_type' => 'accountants',
                    'target_ids' => $lockedOrder->accountant_id
                        ? [$lockedOrder->accountant_id]
                        : $lockedOrder->store->accountants()->where('status', 'active')->pluck('id')->all(),
                    'title' => 'إلغاء طلبية بعد ثلاث مراجعات',
                    'message' => 'ألغيت الطلبية بعد استنفاد ثلاث مرات للإعادة والتعديل.',
                    'data' => ['purchase_order_id' => $lockedOrder->id, 'store_id' => $lockedOrder->store_id],
                    'template_key' => 'purchase_order_edit_limit_cancelled',
                    'channel' => 'CARLED',
                ]);

                return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
            }

            if ($returnAction === 'inventory') {
                $lockedOrder->items()->update(['inventory_count_required' => false]);
                foreach ($lockedOrder->items as $item) {
                    if (! $this->requiresInventoryCount($item) || ($itemIds && ! in_array($item->id, $itemIds, true))) {
                        continue;
                    }

                    $item->update([
                        'inventory_count_required' => true,
                        'inventory_changed_after_count' => false,
                    ]);
                }

                if (! $lockedOrder->items()->where('inventory_count_required', true)->exists()) {
                    throw ValidationException::withMessages([
                        'item_ids' => 'المنتجات المحددة غير صالحة للجرد. اختر منتج بيع مرتبطًا بالمخزون.',
                    ]);
                }
            }

            $lockedOrder->update([
                'inventory_review_status' => $returnAction === 'inventory' ? 'returned_to_accountant' : 'returned_for_edit',
                'inventory_review_note' => $note,
                'inventory_returned_at' => now(),
                'workflow_status' => $returnAction === 'inventory' ? 'returned_for_count' : 'returned_for_edit',
                'edit_return_count' => $returnAction === 'edit' ? ((int) $lockedOrder->edit_return_count + 1) : (int) $lockedOrder->edit_return_count,
            ]);
            $this->recordEvent($lockedOrder, $returnAction === 'inventory' ? 'returned_for_count' : 'returned_for_edit', null, $lockedOrder->workflow_status, 'user', $lockedOrder->user_id, $note);

            $this->notifications->afterCommit([
                'sender_id' => $lockedOrder->store->user_id,
                'sender_type' => 'user',
                'target_type' => 'accountants',
                'target_ids' => $lockedOrder->accountant_id
                    ? [$lockedOrder->accountant_id]
                    : $lockedOrder->store->accountants()->where('status', 'active')->pluck('id')->all(),
                'title' => $returnAction === 'inventory' ? 'طلبية توريد معادة للجرد' : 'طلبية توريد معادة للتعديل',
                'message' => $returnAction === 'inventory' ? 'أعاد صاحب المتجر الطلبية للجرد.' : 'أعاد صاحب المتجر الطلبية للتعديل.',
                'data' => ['purchase_order_id' => $lockedOrder->id, 'store_id' => $lockedOrder->store_id],
                'template_key' => 'purchase_order_inventory_returned',
                'channel' => 'CARLED',
            ]);

            return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
        });
    }

    /**
     * حفظ جرد المحاسب مؤقتًا أو إرساله للمالك مع لقطة مخزون النظام وقت الإرسال فقط.
     */
    public function saveInventoryCount(StorePurchaseOrder $order, User $user, array $items, bool $submit = false, ?int $submittedBy = null): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);
        if ($order->inventory_review_status !== 'returned_to_accountant' && $order->inventory_review_status !== 'count_draft') {
            throw ValidationException::withMessages(['order' => 'لا توجد طلبية معادة للجرد حالياً.']);
        }

        return DB::transaction(function () use ($order, $user, $items, $submit, $submittedBy) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedOrder->inventory_review_status, ['returned_to_accountant', 'count_draft'], true)) {
                throw ValidationException::withMessages(['order' => 'لم تعد الطلبية متاحة لحفظ الجرد.']);
            }
            $lockedOrder->load(['items.product', 'items.matchedProduct', 'store']);
            $snapshotAt = now();

            foreach ($lockedOrder->items as $item) {
                if (! $item->inventory_count_required) {
                    continue;
                }

                $incoming = $items[$item->id] ?? [];
                $count = array_key_exists('inventory_count_quantity', $incoming) && $incoming['inventory_count_quantity'] !== ''
                    ? (float) $incoming['inventory_count_quantity']
                    : null;

                if ($submit && $count === null) {
                    throw ValidationException::withMessages(['items' => 'يجب إدخال كمية الجرد لكل منتج مطلوب قبل إرسال الجرد للمالك.']);
                }

                $data = [
                    'inventory_count_quantity' => $count,
                    'inventory_count_unit' => $incoming['inventory_count_unit'] ?? $item->inventory_count_unit ?? $item->product?->quick_sale_default_unit ?? $item->unit_type ?? 'unit',
                    'inventory_count_note' => $incoming['inventory_count_note'] ?? $item->inventory_count_note,
                ];

                if ($submit) {
                    $product = $item->product ?: $item->matchedProduct;
                    $data['system_quantity_snapshot'] = $product ? (float) $product->getRawOriginal('quantity') : null;
                    $data['inventory_snapshot_at'] = $snapshotAt;
                    $data['inventory_count_submitted_at'] = $snapshotAt;
                    $data['inventory_count_submitted_by'] = $submittedBy ?? $user->id;
                    $data['inventory_changed_after_count'] = false;
                    $attempt = min(3, max(1, (int) $item->inventory_count_attempt + 1));
                    $data['inventory_count_attempt'] = $attempt;
                    StorePurchaseOrderCountAttempt::create([
                        'store_purchase_order_id' => $lockedOrder->id,
                        'store_purchase_order_item_id' => $item->id,
                        'attempt' => $attempt,
                        'counted_quantity' => $count,
                        'system_quantity_image' => $data['system_quantity_snapshot'] ?? 0,
                        'unit_type' => $data['inventory_count_unit'],
                        'accountant_id' => $submittedBy,
                        'note' => $data['inventory_count_note'],
                        'submitted_at' => $snapshotAt,
                    ]);
                }

                $item->update($data);
            }

            $lockedOrder->update($submit ? [
                'inventory_review_status' => 'pending_owner_after_count',
                'inventory_submitted_at' => $snapshotAt,
                'inventory_submitted_by' => $submittedBy ?? $user->id,
                'workflow_status' => 'returned_after_count',
            ] : [
                'inventory_review_status' => 'count_draft',
                'inventory_draft_saved_at' => now(),
            ]);
            if ($submit) {
                $this->recordEvent($lockedOrder, 'count_submitted', 'returned_for_count', 'returned_after_count', 'accountant', $submittedBy ?: $user->id);
            }

            return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
        });
    }

    /**
     * اعتماد مراجعة الجرد فقط، ثم السماح بدورة الاستلام/الاعتماد المخزني الحالية دون تسوية فروقات الجرد.
     */
    public function approveInventoryReview(StorePurchaseOrder $order, User $user, ?string $note = null): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);

        return DB::transaction(function () use ($order, $user, $note) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->inventory_review_status !== 'pending_owner_after_count') {
                throw ValidationException::withMessages(['order' => 'يجب إرسال الجرد للمالك قبل اعتماد مراجعة الجرد.']);
            }
            PurchaseOrderWorkflow::assertAllows($lockedOrder, 'approve_inventory_review');
            if ($lockedOrder->items()->where('inventory_count_required', true)->whereNull('inventory_snapshot_at')->exists()) {
                throw ValidationException::withMessages(['order' => 'لا يمكن اعتماد مراجعة الجرد قبل حفظ لقطة المخزون لكل المنتجات المطلوبة.']);
            }
            $from = $lockedOrder->workflow_status;
            $lockedOrder->update([
                'inventory_review_status' => 'approved',
                'inventory_review_note' => $note ?: $lockedOrder->inventory_review_note,
                'workflow_status' => 'pending_owner_review',
            ]);
            $this->recordEvent($lockedOrder, 'count_approved', $from, 'pending_owner_review', 'user', $user->id, $note);

            return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
        });
    }

    private function requiresInventoryCount(StorePurchaseOrderItem $item): bool
    {
        $product = $item->product ?: $item->matchedProduct;

        return (bool) $product && ! $item->add_to_owner_purchases && ! ($product->isOwnerPurchaseOnly() ?? false);
    }

    public function reject(StorePurchaseOrder $order, User $user, string $reason): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);
        if (! trim($reason)) {
            throw ValidationException::withMessages(['rejection_reason' => 'سبب الرفض مطلوب.']);
        }
        return DB::transaction(function () use ($order, $user, $reason) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status !== 'draft') {
                throw ValidationException::withMessages(['order' => 'يمكن رفض الطلبية قبل إرسالها للمورد فقط.']);
            }
            PurchaseOrderWorkflow::assertAllows($lockedOrder, 'reject');
            $from = $lockedOrder->workflow_status;
            $lockedOrder->update(['workflow_status' => 'rejected', 'rejection_reason' => trim($reason), 'rejected_at' => now()]);
            $this->recordEvent($lockedOrder, 'rejected', $from, 'rejected', 'user', $user->id, $reason);
            return $lockedOrder->fresh();
        });
    }

    public function reopen(StorePurchaseOrder $order, User $user): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);
        return DB::transaction(function () use ($order, $user) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->workflow_status !== 'rejected') {
                throw ValidationException::withMessages(['order' => 'يمكن إعادة فتح الطلبية المرفوضة فقط.']);
            }
            PurchaseOrderWorkflow::assertAllows($lockedOrder, 'reopen');
            $lockedOrder->update(['workflow_status' => 'pending_owner_review']);
            $this->recordEvent($lockedOrder, 'reopened', 'rejected', 'pending_owner_review', 'user', $user->id);
            return $lockedOrder->fresh();
        });
    }

    public function rejectCountItems(StorePurchaseOrder $order, User $user, array $itemIds, string $reason): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);
        if ($order->inventory_review_status !== 'pending_owner_after_count') {
            throw ValidationException::withMessages(['order' => 'يمكن إعادة المنتجات للجرد بعد وصول نتيجة الجرد من المحاسب فقط.']);
        }
        if (! trim($reason) || empty($itemIds)) {
            throw ValidationException::withMessages(['items' => 'حدد منتجًا وأدخل سبب رفض الجرد.']);
        }
        DB::transaction(function () use ($order, $user, $itemIds, $reason): void {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->inventory_review_status !== 'pending_owner_after_count') {
                throw ValidationException::withMessages(['order' => 'لم تعد الطلبية متاحة لرفض نتيجة الجرد.']);
            }
            $lockedOrder->items()->where('inventory_count_required', true)->whereNotIn('id', $itemIds)->update(['inventory_count_required' => false]);
            foreach ($lockedOrder->items()->whereIn('id', $itemIds)->where('add_to_owner_purchases', false)->lockForUpdate()->get() as $item) {
                if (! $this->requiresInventoryCount($item)) {
                    continue;
                }
                if ($item->inventory_count_attempt >= 3) {
                    $item->update(['excluded_after_count' => true, 'excluded_at' => now(), 'exclusion_reason' => $reason, 'inventory_count_required' => false]);
                    $this->recordEvent($lockedOrder, 'item_excluded_after_count', null, null, 'user', $user->id, $reason, ['item_id' => $item->id], $item->id);
                } else {
                    $item->update(['inventory_count_required' => true]);
                }
            }
            $lockedOrder->update(['inventory_review_status' => 'returned_to_accountant', 'workflow_status' => 'returned_for_count', 'inventory_review_note' => $reason]);
        });
        return $order->fresh(['items']);
    }

    public function restoreExcludedItem(StorePurchaseOrder $order, StorePurchaseOrderItem $item, User $user): StorePurchaseOrderItem
    {
        $this->ensureOwner($order->store, $user);
        return DB::transaction(function () use ($order, $item, $user) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status !== 'draft') {
                throw ValidationException::withMessages(['order' => 'لا يمكن استعادة بند بعد إرسال الطلبية أو اعتمادها.']);
            }
            $lockedItem = StorePurchaseOrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedItem->store_purchase_order_id === $lockedOrder->id && $lockedItem->excluded_after_count, 404);
            $lockedItem->update(['excluded_after_count' => false, 'excluded_at' => null, 'exclusion_reason' => null]);
            $this->recordEvent($lockedOrder, 'item_restored', null, null, 'user', $user->id, null, ['item_id' => $lockedItem->id], $lockedItem->id);
            return $lockedItem->fresh();
        });
    }

    private function recordEvent(StorePurchaseOrder $order, string $event, ?string $from, ?string $to, string $actorType, int $actorId, ?string $note = null, array $data = [], ?int $itemId = null): void
    {
        StorePurchaseOrderEvent::create([
            'store_purchase_order_id' => $order->id,
            'store_purchase_order_item_id' => $itemId,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => $note,
            'data' => $data ?: null,
        ]);
    }


    /**
     * إلغاء الطلبية مسموح قبل تسجيل الاستلام فقط، حتى لا يتم إلغاء طلبية لها بيانات استلام قيد المراجعة.
     */
    public function cancel(StorePurchaseOrder $order, User $user): StorePurchaseOrder
    {
        $this->ensureOwner($order->store, $user);
        return DB::transaction(function () use ($order, $user) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedOrder->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages(['order' => 'يمكن إلغاء الطلبية قبل تسجيل الاستلام فقط.']);
            }
            PurchaseOrderWorkflow::assertAllows($lockedOrder, 'cancel');
            $from = $lockedOrder->workflow_status;
            $lockedOrder->update(['status' => 'cancelled', 'workflow_status' => 'cancelled', 'cancelled_at' => now()]);
            $this->recordEvent($lockedOrder, 'cancelled', $from, 'cancelled', 'user', $user->id);
            return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
        });
    }

    /**
     * يعكس الأثر المخزني والمالي للطلبية المعتمدة مع إبقاء الوثيقة وسجلها للتدقيق.
     */
    public function reverseApproval(StorePurchaseOrder $order, User $admin, string $reason, string $businessDate): StorePurchaseOrder
    {
        if (! $admin->isAdmin()) {
            abort(403);
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['support_note' => 'اكتب سببًا واضحًا للعكس لا يقل عن 10 أحرف.']);
        }

        return DB::transaction(function () use ($order, $admin, $reason, $businessDate) {
            $lockedOrder = StorePurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            PurchaseOrderWorkflow::assertAllows($lockedOrder, 'reverse');
            if ($lockedOrder->reversal_operation_id) {
                throw ValidationException::withMessages(['order' => 'سبق عكس اعتماد هذه الطلبية.']);
            }
            $lockedOrder->load(['items.product', 'items.matchedProduct']);

            foreach ($lockedOrder->items as $item) {
                if ($item->excluded_after_count || (float) ($item->quantity_received ?? 0) <= 0) {
                    continue;
                }

                if ($item->owner_purchase_id) {
                    Purchase::whereKey($item->owner_purchase_id)->first()?->delete();
                    continue;
                }

                $productId = $item->product_id ?: $item->matched_product_id;
                if (! $productId) {
                    continue;
                }
                $product = Product::where('store_id', $lockedOrder->store_id)->whereKey($productId)->lockForUpdate()->firstOrFail();
                $product->decreaseStock(
                    (float) $item->quantity_received,
                    'عكس توريد '.$lockedOrder->referenceCode().' — '.trim($reason),
                    $admin->id,
                    $item->unit_type ?: 'unit',
                    $businessDate
                );

                // لا نستبدل تكلفة غيّرتها عملية أحدث بعد هذه الطلبية.
                if ($item->cost_price_before !== null && $item->cost_price_after !== null
                    && abs((float) $product->cost_price - (float) $item->cost_price_after) < 0.0001) {
                    $product->update(['cost_price' => (float) $item->cost_price_before]);
                }
            }

            $operationId = (string) \Illuminate\Support\Str::uuid();
            $lockedOrder->update([
                'workflow_status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $admin->id,
                'reversal_reason' => trim($reason),
                'reversal_operation_id' => $operationId,
            ]);
            $this->recordEvent($lockedOrder, 'inventory_approval_reversed', 'approved_and_supplied', 'reversed', 'support', $admin->id, trim($reason), [
                'business_date' => $businessDate,
                'reversal_operation_id' => $operationId,
            ]);

            return $lockedOrder->fresh(['store', 'items.product', 'items.matchedProduct']);
        });
    }



    /**
     * إنشاء/إعادة إنشاء بنود المسودة من المنتجات النظامية والمنتجات المخصصة.
     * المنتجات النظامية تحفظ بتكلفة محسوبة من تكلفة المنتج الحالية، أما المخصصة فتحفظ بالتكلفة المدخلة إن وجدت.
     */
    private function replaceDraftItems(StorePurchaseOrder $order, Store $store, array $payload): void
    {
        foreach ($payload['items'] as $item) {
            $product = Product::where('store_id', $store->id)->find($item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(['items' => 'أحد المنتجات لا يتبع هذا المتجر.']);
            }

            StorePurchaseOrderItem::create([
                'store_purchase_order_id' => $order->id,
                'product_id' => $product->id,
                'quantity_requested' => (float) ($item['quantity_requested'] ?? 0),
                'unit_type' => $item['unit_type'] ?? 'unit',
                // عند تعديل المنتج نفسه قبل اعتماد الطلبية، نعيد احتساب التكلفة من بيانات المنتج الحالية.
                'cost_price_at_order' => $this->costCalculator->orderLineCost($product, (float) ($item['quantity_requested'] ?? 0), $item['unit_type'] ?? 'unit'),
                'add_to_owner_purchases' => $product->isOwnerPurchaseOnly(),
                'receipt_notes' => $item['receipt_notes'] ?? null,
            ]);
        }

        foreach ($payload['custom_items'] ?? [] as $item) {
            StorePurchaseOrderItem::create([
                'store_purchase_order_id' => $order->id,
                'custom_product_name' => $item['custom_product_name'],
                'quantity_requested' => (float) ($item['quantity_requested'] ?? 0),
                'unit_type' => $item['unit_type'] ?? 'unit',
                'items_per_unit' => $item['items_per_unit'] ?? null,
                'roll_length' => $item['roll_length'] ?? null,
                'cost_price_at_order' => $item['cost_price_at_order'] ?? 0,
                'add_to_owner_purchases' => ! empty($item['add_to_owner_purchases']),
                'receipt_notes' => $item['receipt_notes'] ?? null,
            ]);
        }
    }

    /**
     * حماية مركزية للتأكد أن مالك المتجر الحالي هو صاحب العملية.
     */
    private function ensureOwner(Store $store, User $user): void
    {
        if ((int) $store->user_id !== (int) $user->id) {
            abort(403);
        }
    }
}
